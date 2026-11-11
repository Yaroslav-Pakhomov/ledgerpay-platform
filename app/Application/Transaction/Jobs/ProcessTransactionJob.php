<?php

declare(strict_types=1);

namespace App\Application\Transaction\Jobs;

use App\Application\Audit\Services\AuditLoggerService;
use App\Application\ReadModel\Services\ReadModelProjectionService;
use App\Application\Tracing\Middleware\TraceQueuedJob;
use App\Application\Tracing\Services\TracingService;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;

/**
 * Infrastructure job для асинхронной обработки агрегата Transaction.
 *
 * Связывает прикладной слой (TransactionService) с доменной обработкой
 * в TransactionProcessorService. Job не содержит бизнес-логики —
 * только загрузку агрегата, проверку статуса и делегирование в processor.
 *
 * Доменные ошибки (InsufficientFundsException, InactiveAccountException,
 * CurrencyMismatchException, SameAccountTransferException) пробрасываются
 * из processor → retry ×5 → failed() записывает Failed, outbox {@see TransactionFailed}
 * и audit {@see AuditAction::TransactionFailed}.
 *
 * Трассировка: {@see TraceQueuedJob} (`ledgerpay.transaction.process`, родитель из контекста Laravel
 * при dispatch); внутри {@see self::handle()} — внутренние сегменты `ledgerpay.transaction.processor`
 * и `ledgerpay.read_model.project` через {@see TracingService::withinSpan}.
 */
#[Backoff(10)]
#[Tries(5)]
final class ProcessTransactionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $transactionId,
    ) {
        // Название очереди
        $this->onQueue('transactions');
    }

    /**
     * {@see TraceQueuedJob} — сегмент потребителя (`ledgerpay.transaction.process`, родитель из контекста
     * Laravel при dispatch).
     *
     * {@see WithoutOverlapping} защищает от параллельной обработки одной и той же transaction.
     * DB-level lockForUpdate в {@see TransactionProcessorService} остаётся главным механизмом
     * консистентности баланса; прослойка снижает лишнюю конкуренцию на уровне очереди:
     *
     * - releaseAfter(10) — отложить повторную попытку при overlap;
     * - expireAfter(60) — снять блокировку, если worker упал без release.
     *
     * @return array<int, TraceQueuedJob|WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            // Сначала трассировка — Context заполнен до handle(); WithoutOverlapping не создаёт сегмент.
            new TraceQueuedJob(operation: 'ledgerpay.transaction.process'),
            new WithoutOverlapping('transaction:' . $this->transactionId)->releaseAfter(10)->expireAfter(60),
        ];
    }

    /**
     * Загружает транзакцию, при необходимости обрабатывает в processor и проецирует read model, пишет audit.
     *
     * Сценарий: {@see TransactionProcessorService::process} и {@see ReadModelProjectionService::projectTransactionAndAccounts}
     * во внутренних сегментах; затем {@see AuditAction::TransactionCompleted} (без HTTP-контекста, `actor_user_id` = null).
     * В логах — `trace_id` / `span_id` из контекста Laravel (после {@see TraceQueuedJob}).
     *
     * @throws Throwable
     */
    public function handle(TransactionProcessorService $transactionProcessor, ReadModelProjectionService $projectionService, AuditLoggerService $auditService): void
    {
        Log::info('Transaction processing started.', [
            'transaction_id' => $this->transactionId,
            // trace_id/span_id из TraceQueuedJob + TracingService (если пусто — смотри propagation при dispatch).
            'trace_id' => Context::get('trace_id'),
            'span_id'  => Context::get('span_id'),
        ]);

        $transaction = Transaction::query()->find($this->transactionId);

        if (!$transaction instanceof Transaction) {
            throw new ModelNotFoundException('Транзакция не найдена.');
        }

        /**
         * Идемпотентность worker: Completed / Failed не обрабатываем повторно
         * (ручной retry переводит Failed → Pending).
         */
        if ($transaction->status === TransactionStatus::Completed) {
            return;
        }

        if ($transaction->status === TransactionStatus::Failed) {
            return;
        }

        // Родительский CONSUMER-сегмент уже активен; incoming: null — дочерние INTERNAL в той же трассировке.
        $tracing = app(TracingService::class);

        // Деньги, ledger, outbox transaction.completed — внутри одного внутреннего сегмента.
        $processed = $tracing->withinSpan(
            name: 'ledgerpay.transaction.processor',
            callback: fn () => $transactionProcessor->process($transaction),
            incoming: null,
            kind: SpanKind::KIND_INTERNAL,
            attributes: [
                'transaction_id' => (string) $this->transactionId,
            ],
        );

        // Read model отдельно в Jaeger: видно latency проекции после успешного processor.
        $tracing->withinSpan(
            name: 'ledgerpay.read_model.project',
            callback: fn () => $projectionService->projectTransactionAndAccounts($processed),
            kind: SpanKind::KIND_INTERNAL,
            attributes: [
                'transaction_id' => (string) $processed->id,
            ],
        );

        // Audit после обоих шагов — в записи уже актуальный trace_id из Context.
        $auditService->log(
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
            'trace_id'       => Context::get('trace_id'),
            'span_id'        => Context::get('span_id'),
        ]);
    }

    /**
     * Фиксирует терминальный статус Failed на агрегате после исчерпания retry.
     *
     * В одной DB-транзакции: Failed + outbox {@see \App\Domain\Transaction\Events\TransactionFailed}.
     * Затем — {@see AuditAction::TransactionFailed} через {@see AuditLoggerService}.
     * В логе `trace_id` / `span_id` могут отсутствовать (вызов вне цепочки {@see TraceQueuedJob}).
     *
     * @throws Throwable
     */
    public function failed(Throwable $exception): void
    {
        $transaction = Transaction::query()->find($this->transactionId);

        if (!$transaction instanceof Transaction) {
            return;
        }

        /** Уже Failed — outbox-событие не дублируем. */
        if ($transaction->status === TransactionStatus::Failed) {
            return;
        }

        $transactionProcessor = app(TransactionProcessorService::class);

        $failedTransaction = DB::transaction(
            fn (): Transaction => $transactionProcessor->failWithOutbox($transaction, $exception),
        );

        app(AuditLoggerService::class)->log(
            auditAction: AuditAction::TransactionFailed,
            entity: $failedTransaction,
            metadata: [
                'exception_class' => $exception::class,
                'message'         => $exception->getMessage(),
            ],
        );

        app(ReadModelProjectionService::class)->projectTransactionAndAccounts($failedTransaction);

        Log::error('Transaction processing failed.', [
            'transaction_id'  => $this->transactionId,
            'exception_class' => $exception::class,
            'message'         => $exception->getMessage(),
            'trace_id'        => Context::get('trace_id'),
            'span_id'         => Context::get('span_id'),
        ]);
    }
}
