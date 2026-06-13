<?php

declare(strict_types=1);

namespace App\Application\Transaction\Services;

use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\DTO\CreateTransferData;
use App\Application\Transaction\DTO\CreateWithdrawalData;
use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Application Service для создания и постановки транзакций в очередь.
 *
 *  HTTP-слой создает транзакцию в статусе Pending и dispatch'ит job.
 *  Фактическое движение денег выполняет TransactionProcessorService в worker'е.
 */
final readonly class TransactionService
{
    /**
     * Создает операцию пополнения счета.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     */
    public function deposit(CreateDepositData $data): Transaction
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

        $transaction = $this->createTransactionOnce(
            $data->idempotencyKey,
            $callbackTransaction
        );

        $this->dispatchIfPending($transaction);

        return $transaction->refresh();
    }

    /**
     * Создает операцию списания денежных средств.
     */
    public function withdraw(CreateWithdrawalData $data): Transaction
    {
        $callbackTransaction = function () use ($data): Transaction {
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

        $transaction = $this->createTransactionOnce(
            $data->idempotencyKey,
            $callbackTransaction
        );

        $this->dispatchIfPending($transaction);

        return $transaction->refresh();
    }

    /**
     * Создает операцию перевода между счетами.
     */
    public function transfer(CreateTransferData $data): Transaction
    {
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

        $existing = $this->findByIdempotencyKey($data->idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        $transaction = $this->createTransactionOnce(
            $data->idempotencyKey,
            $callbackTransaction
        );

        $this->dispatchIfPending($transaction);

        return $transaction->refresh();
    }

    /**
     * Создает повторную операцию failed-транзакци.
     */
    public function retry(string $transactionUuid): Transaction
    {
        $transaction = Transaction::query()
            ->where('uuid', $transactionUuid)
            ->firstOrFail();

        if ($transaction->status !== TransactionStatus::Failed) {
            return $transaction;
        }

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
     */
    private function createTransactionOnce(string $idempotencyKey, callable $callbackTransaction): Transaction
    {
        $existing = $this->findByIdempotencyKey($idempotencyKey);

        /**
         * Идемпотентность гарантирует,
         * что повторный запрос не приведет
         * к повторному движению денежных средств.
         */
        if ($existing instanceof Transaction) {
            return $existing;
        }

        return DB::transaction(function () use ($idempotencyKey, $callbackTransaction): Transaction {
            $existing = Transaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Transaction) {
                return $existing;
            }

            return $callbackTransaction();
        });
    }

    private function dispatchIfPending(Transaction $transaction): void
    {

        if ($transaction->status !== TransactionStatus::Pending) {
            return;
        }

        ProcessTransactionJob::dispatch($transaction->id);
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
