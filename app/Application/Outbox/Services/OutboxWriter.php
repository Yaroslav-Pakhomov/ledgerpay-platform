<?php

declare(strict_types=1);

namespace App\Application\Outbox\Services;

use App\Application\Outbox\Jobs\PublishOutboxMessageJob;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

/**
 * Application-сервис записи доменных событий в transactional outbox.
 *
 * Единая точка INSERT в {@see OutboxMessage}. Вызывается из
 * {@see TransactionService} и
 * {@see TransactionProcessorService}
 * **внутри** DB-транзакции — до commit бизнес-изменения.
 *
 * Writer не dispatch'ит jobs и не публикует во внешний broker;
 * это ответственность scheduler + {@see PublishOutboxMessageJob}.
 */
final class OutboxWriter
{
    /**
     * Создаёт pending-запись outbox для доменного события.
     *
     * В headers автоматически добавляются:
     * - `request_id` из {@see Context} (если есть HTTP-запрос);
     * - `occurred_at` — ISO8601 timestamp момента записи.
     *
     * @param  string               $eventName Имя события, например transaction.created
     * @param  Model                $aggregate Затронутый агрегат ({@see Transaction} и т.д.)
     * @param  array<string, mixed> $payload   Тело события для downstream-потребителей
     * @param  array<string, mixed> $headers   Дополнительные метаданные
     * @return OutboxMessage        Созданная запись со status {@see OutboxStatus::Pending}
     */
    public function record(string $eventName, Model $aggregate, array $payload, array $headers = []): OutboxMessage
    {
        $headers = array_merge([
            'request_id'  => Context::get('request_id'),
            'occurred_at' => now()->toISOString(),
        ], $headers);

        return OutboxMessage::query()->create([
            'event_name'     => $eventName,
            'aggregate_type' => $aggregate::class,
            'aggregate_id'   => $aggregate->getKey(),
            'aggregate_uuid' => $aggregate->uuid ?? null,
            'payload'        => $payload,
            'headers'        => $headers,
            'status'         => OutboxStatus::Pending->value,
            'available_at'   => now(),
        ]);

    }
}
