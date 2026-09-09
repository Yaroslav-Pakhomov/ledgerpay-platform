<?php

declare(strict_types=1);

namespace App\Application\Transaction\Services;

use App\Application\Outbox\Services\OutboxWriter;
use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\DTO\CreateTransferData;
use App\Application\Transaction\DTO\CreateWithdrawalData;
use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Application\Transaction\Results\TransactionCreationResult;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Application Service для создания и постановки транзакций в очередь.
 *
 * HTTP-слой создает транзакцию в статусе Pending, записывает outbox-событие
 * `transaction.created` через {@see OutboxWriter} (в той же DB-транзакции)
 * и dispatch'ит {@see ProcessTransactionJob}.
 *
 * Фактическое движение денег выполняет {@see TransactionProcessorService} в worker'е.
 */
final readonly class TransactionService
{
    public function __construct(
        private OutboxWriter $outboxWriter,
    ) {}

    /**
     * Создает операцию пополнения счета.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     *
     * @throws Throwable
     */
    public function deposit(CreateDepositData $data): TransactionCreationResult
    {
        /**
         * Создаем транзакцию в состоянии Pending.
         * Фактическое изменение баланса будет выполнено
         * TransactionProcessorService.
         */
        $callbackTransaction = function () use ($data): Transaction {
            /**
             * Получаем агрегат счета по его публичному идентификатору.
             */
            $target = Account::query()
                ->where('uuid', $data->targetAccountUuid)
                ->firstOrFail();

            return Transaction::query()->create([
                'type'              => TransactionType::Deposit,
                'status'            => TransactionStatus::Pending,
                'source_account_id' => null,
                'target_account_id' => $target->id,
                'amount'            => $data->amount,
                'currency'          => strtoupper($data->currency),
                'idempotency_key'   => $data->idempotencyKey,
            ]);
        };

        $result = $this->createTransactionOnce(
            $data->idempotencyKey,
            $callbackTransaction
        );

        $this->dispatchIfNewPending($result);

        return new TransactionCreationResult(
            transaction: $result->transaction->refresh(),
            created: $result->created,
        );
    }

    /**
     * Создает операцию списания денежных средств.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     *
     * @throws Throwable
     */
    public function withdraw(CreateWithdrawalData $data): TransactionCreationResult
    {
        /**
         * Создаем транзакцию в состоянии Pending.
         * Проверка достаточности средств выполняется позже
         * в TransactionProcessorService при обработке job.
         */
        $callbackTransaction = function () use ($data): Transaction {
            /**
             * Получаем агрегат счета-источника по публичному UUID.
             */
            $source = Account::query()
                ->where('uuid', $data->sourceAccountUuid)
                ->firstOrFail();

            return Transaction::query()->create([
                'type'              => TransactionType::Withdrawal,
                'status'            => TransactionStatus::Pending,
                'source_account_id' => $source->id,
                'target_account_id' => null,
                'amount'            => $data->amount,
                'currency'          => strtoupper($data->currency),
                'idempotency_key'   => $data->idempotencyKey,
            ]);
        };

        $result = $this->createTransactionOnce(
            $data->idempotencyKey,
            $callbackTransaction
        );

        $this->dispatchIfNewPending($result);

        return new TransactionCreationResult(
            transaction: $result->transaction->refresh(),
            created: $result->created,
        );
    }

    /**
     * Создает операцию перевода между счетами.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     *
     * @throws Throwable
     */
    public function transfer(CreateTransferData $data): TransactionCreationResult
    {
        /**
         * Создаем транзакцию в состоянии Pending.
         * Доменные инварианты перевода (активность счетов, валюта,
         * запрет same-account transfer) проверяются в TransactionProcessorService.
         */
        $callbackTransaction = function () use ($data): Transaction {
            /**
             * Получаем агрегаты обоих счетов,
             * участвующих в переводе.
             */
            $source = Account::query()
                ->where('uuid', $data->sourceAccountUuid)
                ->firstOrFail();

            $target = Account::query()
                ->where('uuid', $data->targetAccountUuid)
                ->firstOrFail();

            return Transaction::query()->create([
                'type'              => TransactionType::Transfer,
                'status'            => TransactionStatus::Pending,
                'source_account_id' => $source->id,
                'target_account_id' => $target->id,
                'amount'            => $data->amount,
                'currency'          => strtoupper($data->currency),
                'idempotency_key'   => $data->idempotencyKey,
            ]);
        };

        $result = $this->createTransactionOnce(
            $data->idempotencyKey,
            $callbackTransaction
        );

        $this->dispatchIfNewPending($result);

        return new TransactionCreationResult(
            transaction: $result->transaction->refresh(),
            created: $result->created,
        );
    }

    /**
     * Повторно ставит failed-транзакцию в очередь на обработку.
     *
     * Application use case для ручного retry: переводит агрегат
     * из Failed обратно в Pending и dispatch'ит job.
     * Повторная обработка допустима только для failed-транзакций.
     */
    public function retry(string $transactionUuid): Transaction
    {
        $transaction = Transaction::query()
            ->where('uuid', $transactionUuid)
            ->firstOrFail();

        if ($transaction->status !== TransactionStatus::Failed) {
            return $transaction;
        }

        /**
         * Сбрасываем статус агрегата перед повторной постановкой в очередь.
         * failure_reason очищается, чтобы worker мог обработать транзакцию заново.
         */
        $transaction->update([
            'status'         => TransactionStatus::Pending,
            'failure_reason' => null,
        ]);

        ProcessTransactionJob::dispatch($transaction->id);

        return $transaction->refresh();
    }

    /**
     * Конкурентная идемпотентность.
     *
     * Сначала смотрим существующую запись.
     * Потом создаем внутри DB transaction.
     * Unique index на idempotency_key остается финальной защитой от race condition.
     *
     * @throws Throwable
     */
    private function createTransactionOnce(string $idempotencyKey, callable $callbackTransaction): TransactionCreationResult
    {
        $existing = $this->findByIdempotencyKey($idempotencyKey);

        /**
         * Идемпотентность гарантирует,
         * что повторный запрос не приведет
         * к повторному движению денежных средств.
         */
        if ($existing instanceof Transaction) {
            return new TransactionCreationResult(
                transaction: $existing,
                created: false,
            );

        }

        return DB::transaction(function () use ($idempotencyKey, $callbackTransaction): TransactionCreationResult {
            /**
             * Пессимистическая блокировка под конкурентные idempotency-запросы.
             * Вместе с unique index на idempotency_key гарантирует,
             * что будет создан ровно один агрегат Transaction.
             */
            $existing = Transaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Transaction) {
                return new TransactionCreationResult(
                    transaction: $existing,
                    created: false,
                );
            }

            $transaction = $callbackTransaction();

            /**
             * Outbox: transaction.created — в той же DB-транзакции, что и INSERT transaction.
             * Гарантирует, что downstream узнает о создании операции после commit.
             */
            $this->outboxWriter->record(
                eventName: 'transaction.created',
                aggregate: $transaction,
                payload: [
                    'transaction_uuid'  => $transaction->uuid,
                    'type'              => $transaction->type->value,
                    'amount'            => $transaction->amount,
                    'currency'          => $transaction->currency,
                    'source_account_id' => $transaction->source_account_id,
                    'target_account_id' => $transaction->target_account_id,
                ],
            );

            return new TransactionCreationResult(
                transaction: $transaction,
                created: true,
            );
        });
    }

    /**
     * Ставит в очередь обработку только что созданной pending-транзакции.
     *
     * При повторном idempotency-запросе ($result->created === false)
     * job не dispatch'ится повторно — иначе одна и та же транзакция
     * могла бы обрабатываться несколько раз, пока status === Pending.
     */
    private function dispatchIfNewPending(TransactionCreationResult $result): void
    {
        if (!$result->created) {
            return;
        }

        if ($result->transaction->status !== TransactionStatus::Pending) {
            return;
        }

        ProcessTransactionJob::dispatch($result->transaction->id);
    }

    /**
     * Выполняет поиск ранее созданной транзакции
     * по ключу идемпотентности.
     */
    private function findByIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return Transaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }
}
