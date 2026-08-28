<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Проверяет защитные ограничения на уровне базы данных.
 *
 * Цель этих тестов — убедиться, что критические бизнес-инварианты
 * защищены не только кодом приложения, но и самой БД:
 *
 * - баланс аккаунта не может быть отрицательным;
 * - транзакции должны иметь корректную структуру;
 * - ledger-записи должны содержать положительную сумму;
 * - ledger_entries нельзя изменять напрямую;
 * - audit_logs нельзя удалять напрямую.
 *
 * Если приложение или сторонний код попытается обойти бизнес-логику
 * и выполнить некорректный SQL-запрос напрямую, база данных должна
 * отклонить такую операцию.
 */
final class DatabaseHardeningTest extends TestCase
{
    /**
     * Перед каждым тестом база данных пересоздаётся
     * в чистом состоянии с применёнными миграциями.
     */
    use RefreshDatabase;

    /**
     * Проверяем CHECK-ограничение для баланса аккаунта.
     *
     * Баланс аккаунта не должен быть отрицательным.
     * Даже если попытаться сохранить такое значение напрямую через модель,
     * база данных должна отклонить INSERT.
     */
    public function test_database_rejects_negative_account_balance(): void
    {
        // Ожидаем ошибку уровня базы данных из-за нарушения ограничения.
        $this->expectException(QueryException::class);

        // Пытаемся создать аккаунт с недопустимым отрицательным балансом.
        Account::factory()->create([
            'balance' => -1,
        ]);
    }

    /**
     * Проверяем ограничение на корректную структуру транзакции.
     *
     * Вклад-транзакция должна пополнять целевой счёт
     * и не должна иметь исходный счёт.
     *
     * Здесь намеренно передаём одновременно source_account_id
     * и target_account_id, поэтому БД должна отклонить запись.
     */
    public function test_database_rejects_invalid_transaction_shape(): void
    {
        // Создаём два существующих аккаунта для формирования
        // заведомо некорректной deposit-транзакции.
        $source = Account::factory()->create();
        $target = Account::factory()->create();

        // Ожидаем нарушение ограничения целостности БД.
        $this->expectException(QueryException::class);

        Transaction::query()->create([
            'type'   => TransactionType::Deposit,
            'status' => TransactionStatus::Pending,

            // Для Deposit source_account_id должен быть null.
            // Указываем его намеренно, чтобы проверить защиту БД.
            'source_account_id' => $source->id,

            'target_account_id' => $target->id,
            'amount'            => 1000,
            'currency'          => 'RUB',
            'idempotency_key'   => 'invalid-shape-test',
        ]);
    }

    /**
     * Проверяем ограничение на сумму ledger-записи.
     *
     * Ledger entry представляет реальное движение денежных средств,
     * поэтому сумма д. б. строго больше нуля.
     *
     * Попытка создать запись с amount = 0 должна быть отклонена БД.
     */
    public function test_database_rejects_zero_ledger_amount(): void
    {
        // Аккаунт, на который относится ledger-запись.
        $account = Account::factory()->create();

        // Создаём валидную deposit-транзакцию,
        // к которой будет привязана ledger-запись.
        $transaction = Transaction::factory()->create([
            'type'              => TransactionType::Deposit,
            'source_account_id' => null,
            'target_account_id' => $account->id,
        ]);

        // Ожидаем ошибку из-за нарушения CHECK-ограничения amount > 0.
        $this->expectException(QueryException::class);

        LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,

            // Некорректное значение, используемое специально для теста.
            'amount' => 0,

            'currency'      => 'RUB',
            'balance_after' => $account->balance,
        ]);
    }

    /**
     * Проверяем неизменяемость ledger_entries.
     *
     * Ledger — финансовый журнал, поэтому уже созданные записи
     * не должны изменяться задним числом.
     *
     * Триггер базы данных должен запрещать UPDATE,
     * даже если обновление выполняется напрямую через Query Builder,
     * в обход Eloquent-модели и бизнес-логики приложения.
     */
    public function test_database_trigger_prevents_raw_ledger_update(): void
    {
        // Создаём аккаунт с балансом 1000.
        $account = Account::factory()
            ->withBalance(1000)
            ->create();

        // Создаём транзакцию, к которой относится ledger-запись.
        $transaction = Transaction::factory()->create([
            'type'              => TransactionType::Deposit,
            'source_account_id' => null,
            'target_account_id' => $account->id,
        ]);

        // Создаём валидную запись финансового журнала.
        $entry = LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => 1000,
            'currency'       => 'RUB',
            'balance_after'  => 1000,
        ]);

        // Триггер должен заблокировать попытку изменения существующей записи.
        $this->expectException(QueryException::class);

        // Выполняем UPDATE напрямую через Query Builder,
        // намеренно обходя Eloquent-модель.
        DB::table('ledger_entries')
            ->where('id', $entry->id)
            ->update([
                'amount' => 2000,
            ]);
    }

    /**
     * Проверяем неизменяемость audit_logs.
     *
     * Audit log должен хранить историю действий без возможности
     * удаления записей задним числом.
     *
     * Триггер БД должен запрещать DELETE независимо от того,
     * выполняется операция через модель или напрямую через SQL/Query Builder.
     */
    public function test_database_trigger_prevents_raw_audit_delete(): void
    {
        // Создаём запись аудита, которую затем попробуем удалить.
        $log = AuditLog::query()->create([
            'action' => AuditAction::UserLoggedIn,
        ]);

        // Ожидаем, что DELETE будет заблокирован триггером БД.
        $this->expectException(QueryException::class);

        // Пытаемся удалить audit-запись напрямую,
        // обходя возможные ограничения Eloquent-модели.
        DB::table('audit_logs')
            ->where('id', $log->id)
            ->delete();
    }
}
