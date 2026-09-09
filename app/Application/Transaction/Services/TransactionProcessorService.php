<?php

declare(strict_types=1);

namespace App\Application\Transaction\Services;

use App\Application\Ledger\Services\LedgerService;
use App\Application\Outbox\Services\OutboxWriter;
use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use App\Domain\Transaction\Services\TransferPolicy;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Application Service для обработки денежных транзакций.
 *
 * Координирует выполнение доменных операций над счетами:
 * пополнение, списание и перевод между счетами.
 *
 * В рамках DDD этот сервис не является сущностью домена.
 * Его задача — организовать сценарий использования:
 * - выбрать нужный тип обработки;
 * - обеспечить атомарность операции;
 * - заблокировать изменяемые агрегаты;
 * - делегировать доменную логику Account;
 * - зафиксировать результат в immutable ledger;
 * - перевести транзакцию в итоговый статус.
 *
 * Все денежные операции выполняются внутри DB transaction,
 * чтобы изменение баланса, создание ledger-записей, запись outbox-события
 * `transaction.completed` и смена статуса происходили как единое атомарное действие.
 *
 * Терминальный сбой обработки (`transaction.failed`) фиксируется через
 * {@see self::failWithOutbox()} из {@see ProcessTransactionJob::failed()}.
 */
final readonly class TransactionProcessorService
{
    public function __construct(
        private LedgerService $ledger,
        private TransferPolicy $transferPolicy,
        private OutboxWriter $outboxWriter,
    ) {}

    /**
     * Запускает обработку транзакции в зависимости от ее доменного типа.
     *
     * @throws Throwable
     */
    public function process(Transaction $transaction): Transaction
    {
        return match ($transaction->type) {
            TransactionType::Deposit    => $this->processDeposit($transaction),
            TransactionType::Withdrawal => $this->processWithdrawal($transaction),
            TransactionType::Transfer   => $this->processTransfer($transaction),
        };
    }

    /**
     * Обрабатывает пополнение счета.
     *
     * Пополнение увеличивает баланс целевого счета
     * и создает credit-запись в бухгалтерской книге.
     *
     * @throws Throwable
     */
    private function processDeposit(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction): Transaction {
            /**
             * Блокируем саму транзакцию, чтобы один и тот же платеж
             * не мог быть обработан параллельно несколькими процессами.
             */
            $transaction = $this->lockTransaction($transaction);

            /**
             * Повторный вызов уже завершенной операции не должен
             * повторно изменять баланс счета.
             */
            if ($transaction->status === TransactionStatus::Completed) {
                return $transaction;
            }

            $transaction->update([
                'status' => TransactionStatus::Processing,
            ]);

            /**
             * Блокируем целевой счет на время изменения баланса.
             */
            $target = $this->lockAccount((int) $transaction->target_account_id);

            $target->credit($transaction->amount, $transaction->currency);
            $target->save();

            /**
             * Ledger фиксирует факт зачисления и баланс после операции.
             */
            $this->ledger->credit(
                transaction: $transaction,
                account: $target,
                amount: $transaction->amount,
            );

            $transaction->update([
                'status'         => TransactionStatus::Completed,
                'processed_at'   => now(),
                'failure_reason' => null,
            ]);

            $this->recordTransactionCompleted($transaction->refresh());

            return $transaction;
        });
    }

    /**
     * Обрабатывает списание средств со счета.
     *
     * Списание уменьшает баланс исходного счета
     * и создает debit-запись в бухгалтерской книге.
     *
     * @throws Throwable
     */
    private function processWithdrawal(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction): Transaction {
            $transaction = $this->lockTransaction($transaction);

            if ($transaction->status === TransactionStatus::Completed) {
                return $transaction;
            }

            $transaction->update([
                'status' => TransactionStatus::Processing,
            ]);

            $source = $this->lockAccount((int) $transaction->source_account_id);

            $source->debit($transaction->amount, $transaction->currency);
            $source->save();

            $this->ledger->debit(
                transaction: $transaction,
                account: $source,
                amount: $transaction->amount,
            );

            $transaction->update([
                'status'         => TransactionStatus::Completed,
                'processed_at'   => now(),
                'failure_reason' => null,
            ]);

            $this->recordTransactionCompleted($transaction->refresh());

            return $transaction;
        });
    }

    /**
     * Обрабатывает перевод между двумя счетами.
     *
     * Перевод является составной доменной операцией:
     * - списание с исходного счета;
     * - зачисление на целевой счет;
     * - создание двух ledger-записей в рамках одной DB transaction.
     *
     * @throws Throwable
     */
    private function processTransfer(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction): Transaction {
            $transaction = $this->lockTransaction($transaction);

            if ($transaction->status === TransactionStatus::Completed) {
                return $transaction;
            }

            $transaction->update([
                'status' => TransactionStatus::Processing,
            ]);

            $sourceAccountId = (int) $transaction->source_account_id;
            $targetAccountId = (int) $transaction->target_account_id;

            /**
             * Блокируем оба счета в стабильном порядке по ID.
             *
             * Это снижает риск deadlock при встречных переводах:
             * например, A -> B и B -> A.
             */
            $lockedAccounts = Account::query()
                ->whereIn('id', [$sourceAccountId, $targetAccountId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Account $source */
            $source = $lockedAccounts->get($sourceAccountId);

            /** @var Account $target */
            $target = $lockedAccounts->get($targetAccountId);

            $this->transferPolicy->assertDifferentAccounts($source, $target);

            $source->debit($transaction->amount, $transaction->currency);
            $target->credit($transaction->amount, $transaction->currency);

            $source->save();
            $target->save();

            $this->ledger->debit(
                transaction: $transaction,
                account: $source,
                amount: $transaction->amount,
            );

            $this->ledger->credit(
                transaction: $transaction,
                account: $target,
                amount: $transaction->amount,
            );

            $transaction->update([
                'status'         => TransactionStatus::Completed,
                'processed_at'   => now(),
                'failure_reason' => null,
            ]);

            $this->recordTransactionCompleted($transaction->refresh());

            return $transaction;
        });
    }

    /**
     * Получает транзакцию с блокировкой строки.
     *
     * Это защищает одну и ту же транзакцию
     * от параллельной обработки.
     */
    private function lockTransaction(Transaction $transaction): Transaction
    {
        return Transaction::query()
            ->whereKey($transaction->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Получает счет с блокировкой строки.
     *
     * Пока активна DB transaction, другие процессы
     * не смогут конкурентно изменить этот же баланс.
     */
    private function lockAccount(int $accountId): Account
    {
        return Account::query()
            ->whereKey($accountId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Переводит транзакцию в Failed и записывает outbox `transaction.failed`.
     *
     * Вызывается из {@see ProcessTransactionJob::failed()}
     * внутри DB-транзакции. Caller обязан проверить, что status !== Failed (idempotency).
     */
    public function failWithOutbox(Transaction $transaction, Throwable $exception): Transaction
    {
        $transaction->update([
            'status'         => TransactionStatus::Failed,
            'failure_reason' => $exception->getMessage(),
        ]);

        $transaction = $transaction->refresh();

        $this->recordTransactionFailed($transaction, $exception);

        return $transaction;
    }

    /**
     * Записывает outbox-событие transaction.completed для downstream-потребителей.
     *
     * Вызывается внутри DB-транзакции processDeposit/Withdrawal/Transfer
     * сразу после перевода агрегата в {@see TransactionStatus::Completed}.
     *
     * @param Transaction $transaction Завершённая транзакция (refresh после update)
     */
    private function recordTransactionCompleted(Transaction $transaction): void
    {
        $this->outboxWriter->record(
            eventName: 'transaction.completed',
            aggregate: $transaction,
            payload: [
                'transaction_uuid'  => $transaction->uuid,
                'type'              => $transaction->type->value,
                'amount'            => $transaction->amount,
                'currency'          => $transaction->currency,
                'source_account_id' => $transaction->source_account_id,
                'target_account_id' => $transaction->target_account_id,
                'processed_at'      => $transaction->processed_at->toISOString(),
            ],
        );
    }

    /**
     * Записывает outbox-событие transaction.failed для downstream-потребителей.
     *
     * @param Transaction $transaction Транзакция с заполненным failure_reason
     * @param Throwable   $exception   Исключение, приведшее к сбою
     */
    private function recordTransactionFailed(Transaction $transaction, Throwable $exception): void
    {
        $this->outboxWriter->record(
            eventName: 'transaction.failed',
            aggregate: $transaction,
            payload: [
                'transaction_uuid'  => $transaction->uuid,
                'type'              => $transaction->type->value,
                'amount'            => $transaction->amount,
                'currency'          => $transaction->currency,
                'source_account_id' => $transaction->source_account_id,
                'target_account_id' => $transaction->target_account_id,
                'failure_reason'    => $transaction->failure_reason,
                'exception_class'   => $exception::class,
            ],
        );
    }
}
