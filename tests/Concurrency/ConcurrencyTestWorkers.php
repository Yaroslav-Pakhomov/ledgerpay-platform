<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Concurrency\ProcessDriver;
use Illuminate\Support\Facades\Concurrency;
use Tests\TestCase;
use Throwable;

/**
 * Точки входа для дочерних процессов {@see Concurrency::run()}.
 *
 * Только статические методы и скалярные аргументы в замыкании — иначе сериализация
 * тянет {@see TestCase} и возможно переполнение стека.
 *
 * Исключения не пробрасываются наружу: {@see ProcessDriver}
 * считает ненулевой код выхода ошибкой набора тестов.
 *
 * @phpstan-type WorkerResult array{
 *     ok: bool,
 *     status?: string,
 *     created?: bool,
 *     transaction_uuid?: string,
 *     exception?: class-string<Throwable>,
 *     message?: string
 * }
 */
final class ConcurrencyTestWorkers
{
    /**
     * Обрабатывает транзакцию через {@see TransactionProcessorService}; ошибки возвращает в массиве.
     *
     * @return WorkerResult
     */
    public static function runProcessorSafely(int $transactionId): array
    {
        try {
            $transaction = Transaction::query()->findOrFail($transactionId);
            $processed   = app(TransactionProcessorService::class)->process($transaction);

            return [
                'ok'     => true,
                'status' => $processed->status->value,
            ];
        } catch (Throwable $exception) {
            return [
                'ok'        => false,
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ];
        }
    }

    /**
     * Параллельные вызовы {@see TransactionService::deposit()} с одним ключом идемпотентности.
     *
     * @return WorkerResult
     */
    public static function runDepositSafely(string $targetAccountUuid, int $amount, string $currency, string $idempotencyKey): array
    {
        try {
            $data = new CreateDepositData(
                targetAccountUuid: $targetAccountUuid,
                amount: $amount,
                currency: $currency,
                idempotencyKey: $idempotencyKey,
            );
            $result = app(TransactionService::class)->deposit($data);

            return [
                'ok'               => true,
                'created'          => $result->created,
                'transaction_uuid' => $result->transaction->uuid,
            ];
        } catch (Throwable $exception) {
            return [
                'ok'        => false,
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ];
        }
    }

    /**
     * Два параллельных запуска {@see ProcessTransactionJob} для одной транзакции (без middleware очереди).
     *
     * @return WorkerResult
     */
    public static function runJobSafely(int $transactionId): array
    {
        try {
            $job = new ProcessTransactionJob($transactionId);
            $job->handle(app(TransactionProcessorService::class));

            return ['ok' => true];
        } catch (Throwable $exception) {
            return [
                'ok'        => false,
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
            ];
        }
    }
}
