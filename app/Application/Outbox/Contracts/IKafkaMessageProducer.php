<?php

declare(strict_types=1);

namespace App\Application\Outbox\Contracts;

use App\Application\Outbox\Services\KafkaMessageProducer;
use App\Application\Outbox\Services\OutboxPublisher;

/**
 * Контракт отправки outbox-envelope во внешний broker (Kafka/Redpanda).
 *
 * Реализация: {@see KafkaMessageProducer}.
 * Вызывается из {@see OutboxPublisher} при `KAFKA_ENABLED=true`.
 */
interface IKafkaMessageProducer
{
    /**
     * Публикует outbox-envelope в Kafka/Redpanda topic.
     *
     * @param string               $topic   Имя topic, например `ledgerpay.domain-events`
     * @param string|null          $key     Partition key (обычно `aggregate_uuid` агрегата)
     * @param array<string, mixed> $body    JSON-envelope для downstream-потребителей
     * @param array<string, mixed> $headers Kafka headers (`outbox_uuid`, `event_name`, …)
     */
    public function publish(
        string $topic,
        ?string $key,
        array $body,
        array $headers = [],
    ): void;
}
