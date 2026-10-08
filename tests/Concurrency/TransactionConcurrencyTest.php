<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Application\Reconciliation\Services\ReconciliationService;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Account\Exceptions\InsufficientFundsException;
use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Database\Factories\AccountFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Concurrency;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concurrency\Concerns\CreatesPendingTransactions;
use Tests\Concurrency\Concerns\RequiresPostgresql;
use Tests\TestCase;
use Throwable;

/**
 * Набор тестов конкурентности на PostgreSQL: несколько PHP-процессов, отдельные сессии БД.
 *
 * Требования:
 * - {@see RequiresPostgresql}
 * - {@see RefreshDatabase} и пустой {@see $connectionsToTransact} — иначе фикстуры
 *   не видны дочерним процессам (транзакция PHPUnit).
 */
final class TransactionConcurrencyTest extends TestCase
{
    use CreatesPendingTransactions;
    use RefreshDatabase;
    use RequiresPostgresql;

    /**
     * Не оборачивает тест в транзакцию БД — данные фиксируются и видны дочерним процессам.
     *
     * @var list<string|null>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgresql();
    }

    /**
     * Два параллельных списания: баланса хватает только на одно.
     * Ожидание: баланс не отрицателен, ровно одна операция {@see TransactionStatus::Completed},
     * вторая не нарушает ограничение CHECK на баланс.
     */
    #[Test]
    #[Group('concurrency')]
    public function test_concurrent_withdrawals_serialize_on_account_row_lock(): void
    {
        $account = Account::factory()->currency('USD')->withBalance(10_000)->create();

        $first  = $this->pendingWithdrawal($account, 7_000, 'conc-withdraw-1');
        $second = $this->pendingWithdrawal($account, 7_000, 'conc-withdraw-2');

        $firstId  = $first->id;
        $secondId = $second->id;

        $results = Concurrency::run([
            'a' => static fn (): array => ConcurrencyTestWorkers::runProcessorSafely($firstId),
            'b' => static fn (): array => ConcurrencyTestWorkers::runProcessorSafely($secondId),
        ], timeout: 60);

        $account->refresh();
        $first->refresh();
        $second->refresh();

        $completed = collect([$first, $second])->filter(fn (Transaction $tx): bool => $tx->status === TransactionStatus::Completed)->count();

        $this->assertSame(10_000 - 7_000, $account->balance);
        $this->assertSame(1, $completed);
        $this->assertTrue(collect($results)->contains(fn (array $r): bool => $r['ok'] === true));
        $this->assertTrue(
            collect($results)->contains(
                fn (array $r): bool => $r['ok'] === false
                    && $r['exception'] === InsufficientFundsException::class,
            ),
        );
    }

    /**
     * Встречные переводы A→B и B→A (риск взаимной блокировки без сортировки счетов по id).
     *
     * @throws Throwable
     */
    #[Test]
    #[Group('concurrency')]
    public function test_concurrent_cross_transfers_complete_without_deadlock(): void
    {
        $accountA = Account::factory()->currency('USD')->withBalance(50_000)->create();
        $accountB = Account::factory()->currency('USD')->withBalance(50_000)->create();

        $this->seedOpeningLedgerBalance($accountA, 50_000, 'conc-open-a');
        $this->seedOpeningLedgerBalance($accountB, 50_000, 'conc-open-b');

        $aToB = $this->pendingTransfer($accountA, $accountB, 20_000, 'conc-a-b');
        $bToA = $this->pendingTransfer($accountB, $accountA, 30_000, 'conc-b-a');

        $aToBId = $aToB->id;
        $bToAId = $bToA->id;

        $results = Concurrency::run([
            'a_to_b' => static fn (): array => ConcurrencyTestWorkers::runProcessorSafely($aToBId),
            'b_to_a' => static fn (): array => ConcurrencyTestWorkers::runProcessorSafely($bToAId),
        ], timeout: 60);

        $accountA->refresh();
        $accountB->refresh();
        $aToB->refresh();
        $bToA->refresh();

        $this->assertTrue(collect($results)->every(fn (array $r): bool => $r['ok'] === true));
        $this->assertSame(TransactionStatus::Completed, $aToB->status);
        $this->assertSame(TransactionStatus::Completed, $bToA->status);
        $this->assertSame(60_000, $accountA->balance);
        $this->assertSame(40_000, $accountB->balance);

        $reportA = app(ReconciliationService::class)->checkAccount($accountA->refresh());
        $reportB = app(ReconciliationService::class)->checkAccount($accountB->refresh());

        $this->assertSame(ReconciliationStatus::Matched, $reportA->status);
        $this->assertSame(ReconciliationStatus::Matched, $reportB->status);
    }

    /**
     * Один ключ идемпотентности, два параллельных пополнения через {@see TransactionService::deposit()}.
     * Ожидание: одна строка в `transactions`, оба успешных ответа с одним UUID.
     */
    #[Test]
    #[Group('concurrency')]
    public function test_concurrent_idempotent_deposit_creates_single_transaction(): void
    {
        $account = Account::factory()->currency('USD')->withBalance(0)->create();
        $key     = 'conc-idempotency-deposit';

        $accountUuid = $account->uuid;

        $results = Concurrency::run([
            'first' => static fn (): array => ConcurrencyTestWorkers::runDepositSafely(
                $accountUuid,
                5_000,
                'USD',
                $key,
            ),
            'second' => static fn (): array => ConcurrencyTestWorkers::runDepositSafely(
                $accountUuid,
                5_000,
                'USD',
                $key,
            ),
        ], timeout: 60);

        $this->assertSame(1, Transaction::query()->where('idempotency_key', $key)->count());

        $successful = collect($results)->filter(fn (array $r): bool => $r['ok'] === true);
        $this->assertGreaterThanOrEqual(1, $successful->count());

        $uuids = $successful->pluck('transaction_uuid')->unique()->values();

        $this->assertCount(1, $uuids);

        $transaction = Transaction::query()->where('idempotency_key', $key)->firstOrFail();
        $account->refresh();
        $transaction->refresh();

        $this->assertSame(TransactionStatus::Completed, $transaction->status);
        $this->assertSame(5_000, $account->balance);
    }

    /**
     * Два параллельных обработчика одной транзакции в {@see TransactionStatus::Pending}
     * (без промежуточного слоя очереди).
     * {@see TransactionProcessorService}: блокировка строки транзакции и досрочный выход при {@see TransactionStatus::Completed}.
     */
    #[Test]
    #[Group('concurrency')]
    public function test_concurrent_double_processing_applies_ledger_once(): void
    {
        $account     = Account::factory()->currency('USD')->withBalance(0)->create();
        $transaction = $this->pendingDeposit($account, 25_000, 'conc-double-process');

        $transactionId = $transaction->id;

        $results = Concurrency::run([
            'worker_1' => static fn (): array => ConcurrencyTestWorkers::runJobSafely($transactionId),
            'worker_2' => static fn (): array => ConcurrencyTestWorkers::runJobSafely($transactionId),
        ], timeout: 60);

        $account->refresh();
        $transaction->refresh();

        $this->assertTrue(collect($results)->every(fn (array $r): bool => $r['ok'] === true));
        $this->assertSame(TransactionStatus::Completed, $transaction->status);
        $this->assertSame(25_000, $account->balance);
        $this->assertSame(
            1,
            LedgerEntry::query()->where('transaction_id', $transaction->id)->count(),
        );
    }

    /**
     * Начальное зачисление в реестре, согласованное с {@see AccountFactory::withBalance()}
     * (для {@see ReconciliationService}).
     *
     * Фабрика задаёт только колонку `balance`, без проводок.
     */
    private function seedOpeningLedgerBalance(Account $account, int $amount, string $idempotencyKey): void
    {
        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Completed,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => $amount,
            'currency'               => $account->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
            'processed_at'           => now(),
        ]);

        LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => $amount,
            'currency'       => $account->currency,
            'balance_after'  => $amount,
        ]);
    }
}
