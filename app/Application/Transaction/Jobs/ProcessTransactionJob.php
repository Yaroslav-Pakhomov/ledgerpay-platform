<?php

declare(strict_types=1);

namespace App\Application\Transaction\Jobs;

use App\Application\Audit\Services\AuditLogger;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Infrastructure job для асинхронной обработки агрегата Transaction.
 *
 * Связывает application layer (TransactionService) с доменной обработкой
 * в TransactionProcessorService. Job не содержит бизнес-логики —
 * только загрузку агрегата, проверку статуса и делегирование в processor.
 *
 * Доменные ошибки (InsufficientFundsException, InactiveAccountException,
 * CurrencyMismatchException, SameAccountTransferException) пробрасываются
 * из processor → retry ×5 → failed() записывает failure_reason в агрегат.
 */
final class ProcessTransactionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 10;

    public function __construct(
        public readonly int $transactionId,
    ) {
        // Название очереди
        $this->onQueue('transactions');
    }

    /**
     * WithoutOverlapping защищает от параллельной обработки одной и той же transaction.
     *
     * DB-level lockForUpdate в TransactionProcessorService остается главным
     * механизмом консистентности баланса, а middleware снижает лишнюю
     * конкуренцию на уровне очереди:
     *
     * - releaseAfter(10) — отложить повторную попытку при overlap;
     * - expireAfter(60) — снять блокировку, если worker упал без release.
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('transaction:'.$this->transactionId)
                ->releaseAfter(10)
                ->expireAfter(60),
        ];
    }

    /**
     * Загружает транзакцию, делегирует обработку в processor, пишет audit.
     *
     * После успеха — {@see AuditAction::TransactionCompleted} через {@see AuditLogger}
     * (без HTTP context, `actor_user_id` = null).
     *
     * @throws Throwable
     */
    public function handle(TransactionProcessorService $processor): void
    {
        Log::info('Transaction processing started.', [
            'transaction_id' => $this->transactionId,
        ]);

        $transaction = Transaction::query()->find($this->transactionId);

        if (!$transaction instanceof Transaction) {
            throw new ModelNotFoundException('Транзакция не найдена.');
        }

        /**
         * Идемпотентность worker'а: уже завершенные или failed-транзакции
         * не обрабатываются повторно (retry endpoint переводит Failed → Pending).
         */
        if ($transaction->status === TransactionStatus::Completed) {
            return;
        }

        if ($transaction->status === TransactionStatus::Failed) {
            return;
        }

        $processed = $processor->process($transaction);

        // Audit: TransactionCompleted — без HTTP context, actor_user_id = null.
        app(AuditLogger::class)->log(
            auditAction: AuditAction::TransactionCompleted,
            entity: $processed,
            metadata: [
                'type'     => $processed->type->value,
                'amount'   => $processed->amount,
                'currency' => $processed->currency,
            ],
        );

        Log::info('Transaction processing completed.', [
            'transaction_id' => $this->transactionId,
        ]);
    }

    /**
     * Фиксирует терминальный статус Failed на агрегате после исчерпания retry.
     *
     * После update агрегата пишет {@see AuditAction::TransactionFailed}
     * через {@see AuditLogger}. `failure_reason` сохраняет доменное
     * сообщение исключения для API и аудита.
     */
    public function failed(Throwable $exception): void
    {
        $transaction = Transaction::query()->find($this->transactionId);

        if (!$transaction instanceof Transaction) {
            return;
        }

        $transaction->update([
            'status'         => TransactionStatus::Failed,
            'failure_reason' => $exception->getMessage(),
        ]);

        app(AuditLogger::class)->log(
            auditAction: AuditAction::TransactionFailed,
            entity: $transaction,
            metadata: [
                'exception_class' => $exception::class,
                'message'         => $exception->getMessage(),
            ],
        );

        Log::error('Transaction processing failed.', [
            'transaction_id'  => $this->transactionId,
            'exception_class' => $exception::class,
            'message'         => $exception->getMessage(),
        ]);
    }
}
