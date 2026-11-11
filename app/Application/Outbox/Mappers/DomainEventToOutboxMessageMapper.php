<?php

declare(strict_types=1);

namespace App\Application\Outbox\Mappers;

use App\Application\Outbox\Services\OutboxWriterService;
use App\Application\Tracing\Services\TracingService;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Shared\Events\IDomainEvent;
use Illuminate\Support\Facades\Context;

/**
 * Преобразует типизированное событие домена в форму для вставки (INSERT) в {@see OutboxMessage}.
 *
 * Централизует метаданные транспортного слоя:
 * - `request_id` из {@see Context};
 * - `occurred_at` — метка времени ISO8601 момента преобразования.
 * - tracing: `traceparent`, `trace_id`, `span_id` через {@see TracingService};
 *
 * Вызывается из {@see OutboxWriterService::recordEvent()}.
 */
final class DomainEventToOutboxMessageMapper
{
    /**
     * @return array{
     *     event_name: string,
     *     aggregate_type: string,
     *     aggregate_id: int,
     *     aggregate_uuid: string|null,
     *     payload: array<string, mixed>,
     *     headers: array<string, mixed>
     * }
     */
    public function map(IDomainEvent $event): array
    {
        $tracing = app(TracingService::class);
        $carrier = $tracing->injectCurrentContext();

        $headers = array_merge(
            $event->headers(),
            array_filter([
                'request_id'  => Context::get('request_id'),
                'traceparent' => $carrier['traceparent'] ?? Context::get('traceparent'),
                'trace_id'    => $tracing->currentTraceId() ?? Context::get('trace_id'),
                'span_id'     => $tracing->currentSpanId() ?? Context::get('span_id'),
                'occurred_at' => now()->toISOString(),
            ], static fn ($value) => $value !== null));

        return [
            'event_name'     => $event->eventName(),
            'aggregate_type' => $event->aggregateType(),
            'aggregate_id'   => $event->aggregateId(),
            'aggregate_uuid' => $event->aggregateUuid(),
            'payload'        => $event->payload(),
            'headers'        => $headers,
        ];
    }
}
