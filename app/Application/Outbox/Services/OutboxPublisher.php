<?php

declare(strict_types=1);

namespace App\Application\Outbox\Services;

use App\Application\Outbox\Contracts\IKafkaMessageProducer;
use App\Application\Outbox\Jobs\PublishOutboxMessageJob;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

/**
 * Application-сервис публикации outbox-сообщения во внешний transport.
 *
 * Вызывается из {@see PublishOutboxMessageJob} после перевода записи
 * в status {@see OutboxStatus::Processing}. После успешного вызова
 * {@see PublishOutboxMessageJob} помечает запись как {@see OutboxStatus::Published}.
 *
 * Transport:
 * - structured log — всегда (observability, dev/CI без broker);
 * - Kafka/Redpanda — при `KAFKA_ENABLED=true` через {@see IKafkaMessageProducer}.
 */
final readonly class OutboxPublisher
{
    public function __construct(
        private IKafkaMessageProducer $kafkaProducer,
    ) {}

    /**
     * Публикует outbox-сообщение: log + опционально Kafka.
     *
     * @param OutboxMessage $outboxMessage Загруженная и заблокированная запись outbox
     *
     * @throws JsonException    Пробрасывается из {@see IKafkaMessageProducer::publish()}
     * @throws RuntimeException Ошибка Kafka publish (broker недоступен и т.д.)
     */
    public function publish(OutboxMessage $outboxMessage): void
    {
        $envelope = $this->buildEnvelope($outboxMessage);

        Log::info('Outbox message published.', $envelope);

        if (config('kafka.enabled')) {
            $this->kafkaProducer->publish(
                topic: (string) config('kafka.topic'),
                key: $outboxMessage->aggregate_uuid,
                body: $envelope,
                headers: $this->buildKafkaHeaders($outboxMessage),
            );
        }
    }

    /**
     * Собирает envelope для log и Kafka (тело сообщения для consumers).
     *
     * @return array<string, mixed>
     */
    private function buildEnvelope(OutboxMessage $outboxMessage): array
    {
        return [
            'outbox_uuid'    => $outboxMessage->uuid,
            'event_name'     => $outboxMessage->event_name,
            'aggregate_type' => $outboxMessage->aggregate_type,
            'aggregate_id'   => $outboxMessage->aggregate_id,
            'aggregate_uuid' => $outboxMessage->aggregate_uuid,
            'payload'        => $outboxMessage->payload,
            'headers'        => $outboxMessage->headers,
            'published_at'   => now()->toISOString(),
        ];
    }

    /**
     * Kafka headers: stored headers outbox + idempotency/dispatch metadata.
     *
     * @return array<string, mixed>
     */
    private function buildKafkaHeaders(OutboxMessage $outboxMessage): array
    {
        $storedHeaders = is_array($outboxMessage->headers) ? $outboxMessage->headers : [];

        return array_merge($storedHeaders, [
            'event_name'   => $outboxMessage->event_name,
            'outbox_uuid'  => $outboxMessage->uuid,
            'content-type' => 'application/json',
        ]);
    }
}
