<?php

declare(strict_types=1);

namespace App\Application\Inbox\Jobs;

use App\Application\Inbox\Services\InboxProcessorService;
use App\Application\Tracing\Middleware\TraceQueuedJob;
use App\Application\Tracing\Support\SpanNames;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Infrastructure job асинхронной обработки inbox-сообщения.
 *
 * Связывает scheduler ({@see DispatchPendingInboxMessagesCommand})
 * с {@see InboxProcessorService}. Доменной логики в job нет — только делегирование по PK.
 *
 * Очередь: {@see config('inbox.queue')} (Horizon / Redis).
 *
 * {@see Tries} 1 и {@see Timeout} 60: повторы и backoff — в {@see InboxProcessorService}
 * и полях `available_at` / `max_attempts`, а не через failed() очереди Laravel.
 *
 * Трассировка: {@see TraceQueuedJob} — операция `ledgerpay.inbox.process`;
 * `$traceparent` из headers при приёме webhook (если передан в dispatch) или контекст Laravel.
 */
#[Timeout(60)]
#[Tries(1)]
final class ProcessInboxMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param int         $inboxMessageId PK {@see InboxMessage}
     * @param string|null $traceparent    W3C traceparent из headers inbox; иначе из контекста Laravel
     */
    public function __construct(
        public readonly int $inboxMessageId,
        public readonly ?string $traceparent = null,
    ) {
        $this->onQueue(config('inbox.queue', 'inbox'));
    }

    /**
     * @return array<int, TraceQueuedJob>
     */
    public function middleware(): array
    {
        return [
            new TraceQueuedJob(
                operation: SpanNames::INBOX_PROCESS,
                traceparent: $this->traceparent,
            ),
        ];
    }

    /**
     * Делегирует обработку {@see InboxProcessorService::process()}.
     */
    public function handle(InboxProcessorService $processor): void
    {
        $processor->process($this->inboxMessageId);
    }
}
