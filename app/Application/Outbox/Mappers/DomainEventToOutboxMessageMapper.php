<?php

declare(strict_types=1);

namespace App\Application\Outbox\Mappers;

use App\Application\Outbox\Services\OutboxWriter;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Shared\Events\IDomainEvent;
use Illuminate\Support\Facades\Context;

/**
 * Преобразует типизированное событие домена в форму для вставки (INSERT) в {@see OutboxMessage}.
 *
 * Централизует метаданные транспортного слоя:
 * - `request_id` из {@see Context};
 * - `occurred_at` — метка времени ISO8601 момента преобразования.
 *
 * Вызывается из {@see OutboxWriter::recordEvent()}.
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
        $headers = array_merge([
            'request_id'  => Context::get('request_id'),
            'occurred_at' => now()->toISOString(),
        ], $event->headers());

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
