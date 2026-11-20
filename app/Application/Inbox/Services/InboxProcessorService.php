<?php

declare(strict_types=1);

namespace App\Application\Inbox\Services;

use App\Domain\Inbox\Enums\InboxStatus;
use App\Domain\Inbox\Models\InboxMessage;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Обработка inbox-сообщения: lock, смена статусов, делегирование {@see InboxHandlerRegistryService}.
 *
 * Точка входа из очереди: {@see ProcessInboxMessageJob::handle()}.
 * Повторная доставка в очередь — {@see DispatchPendingInboxMessagesCommand} (статусы received/failed).
 *
 * Идемпотентность / пропуск:
 * - терминальные статусы ({@see InboxMessage::isTerminal()}) — без изменений;
 * - `available_at` в будущем (backoff после ошибки) — без изменений.
 *
 * Успех: processing → обработчик → processed, сброс lease и ошибок, `attempts++`.
 *
 * Ошибка (включая исключения обработчика): {@see recordFailure()} — failed или dead,
 * экспоненциальный backoff по {@see config('inbox.*')}; исключение также report().
 *
 * Retry на уровне записи inbox, не через повторные Laravel job attempts
 * ({@see ProcessInboxMessageJob} — {@see Tries} 1).
 */
final readonly class InboxProcessorService
{
    public function __construct(
        private InboxHandlerRegistryService $handlers,
    ) {}

    /**
     * Обрабатывает сообщение по PK в транзакции (до 3 попыток commit при deadlock).
     */
    public function process(int $messageId): void
    {
        try {
            DB::transaction(function () use ($messageId): void {
                $message = InboxMessage::query()->whereKey($messageId)->lockForUpdate()->firstOrFail();

                if ($message->isTerminal() || $message->available_at->isFuture()) {
                    return;
                }

                $message->status = InboxStatus::Processing;

                $this->handlers->handle($message);

                $message->status = InboxStatus::Processed;
                $message->attempts++;
                $message->processed_at         = now();
                $message->dispatch_lease_until = null;
                $message->last_error           = null;
                $message->last_error_code      = null;

                $message->save();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->recordFailure($messageId, $exception);
            report($exception);
        }
    }

    /**
     * Увеличивает attempts, переводит в failed/dead, сдвигает available_at, сохраняет last_error*.
     *
     * @throws Throwable пробрасывается из вложенной транзакции при сбое БД
     */
    private function recordFailure(int $messageId, Throwable $exception): void
    {
        DB::transaction(function () use ($messageId, $exception): void {
            $message = InboxMessage::query()->whereKey($messageId)->lockForUpdate()->firstOrFail();

            if ($message->isTerminal()) {
                return;
            }

            $attempts    = $message->attempts + 1;
            $maxAttempts = (int) config('inbox.max_attempts', 5);

            $message->attempts = $attempts;
            $message->status   = $attempts >= $maxAttempts ? InboxStatus::Dead : InboxStatus::Failed;

            $base     = (int) config('inbox.retry_base_seconds', 30);
            $max      = (int) config('inbox.retry_max_seconds', 3600);
            $exponent = min($attempts - 1, 10);

            $delay  = min($max, $base * (2 ** $exponent));
            $jitter = random_int(0, max(1, (int) floor($delay * 0.2)));

            $message->available_at         = now()->addSeconds($delay + $jitter);
            $message->dispatch_lease_until = null;
            $message->last_failed_at       = now();
            $message->last_error_code      = class_basename($exception);
            $message->last_error           = mb_substr($exception->getMessage(), 0, 1000);

            $message->save();
        });
    }
}
