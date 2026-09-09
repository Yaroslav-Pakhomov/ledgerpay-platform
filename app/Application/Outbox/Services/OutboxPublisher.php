<?php

declare(strict_types=1);

namespace App\Application\Outbox\Services;

use App\Application\Outbox\Jobs\PublishOutboxMessageJob;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;

/**
 * Application-сервис публикации outbox-сообщения во внешний transport.
 *
 * Вызывается из {@see PublishOutboxMessageJob} после перевода записи
 * в status {@see OutboxStatus::Processing}.
 *
 * Текущая реализация — structured log (stub для Kafka/SNS/RabbitMQ).
 * При успешном publish job помечает запись как {@see OutboxStatus::Published}.
 */
final class OutboxPublisher
{
    /**
     * Публикует outbox-сообщение.
     *
     * @param OutboxMessage $message Загруженная и заблокированная запись outbox
     */
    public function publish(OutboxMessage $message): void
    {
        Log::info('Outbox message published.', [
            'outbox_uuid'    => $message->uuid,
            'event_name'     => $message->event_name,
            'aggregate_type' => $message->aggregate_type,
            'aggregate_id'   => $message->aggregate_id,
            'aggregate_uuid' => $message->aggregate_uuid,
            'payload'        => $message->payload,
            'headers'        => $message->headers,
        ]);
    }
}
