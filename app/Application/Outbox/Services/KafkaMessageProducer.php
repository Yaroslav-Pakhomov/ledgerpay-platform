<?php

declare(strict_types=1);

namespace App\Application\Outbox\Services;

use App\Application\Outbox\Contracts\IKafkaMessageProducer;
use JsonException;
use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;
use longlang\phpkafka\Protocol\RecordBatch\RecordHeader;
use RuntimeException;
use Throwable;

/**
 * Infrastructure-сервис отправки outbox-envelope в Kafka/Redpanda.
 *
 * Реализует {@see IKafkaMessageProducer}. Вызывается из {@see OutboxPublisher}
 * после structured log (когда `config('kafka.enabled') === true`).
 *
 * Без действия при `config('kafka.enabled') === false` (defense-in-depth:
 * {@see OutboxPublisher} также проверяет flag перед вызовом).
 */
final class KafkaMessageProducer implements IKafkaMessageProducer
{
    /**
     * Сериализует envelope в JSON и отправляет в Kafka/Redpanda.
     *
     * @param string               $topic   Имя topic из `config('kafka.topic')`
     * @param string|null          $key     Partition key для упорядоченности событий агрегата
     * @param array<string, mixed> $body    Envelope ({@see OutboxPublisher::buildEnvelope()})
     * @param array<string, mixed> $headers Kafka headers для idempotency downstream
     *
     * @throws JsonException    Ошибка JSON-сериализации тела сообщения
     * @throws RuntimeException Ошибка отправки в broker (оборачивает исключение клиента)
     */
    public function publish(string $topic, ?string $key, array $body, array $headers = []): void
    {
        if (!config('kafka.enabled')) {
            return;
        }

        $payload = json_encode($body, JSON_THROW_ON_ERROR);

        $kafkaHeaders = [];

        foreach ($headers as $name => $value) {
            if ($value === null) {
                continue;
            }

            $kafkaHeaders[] = new RecordHeader()
                ->setHeaderKey((string) $name)
                ->setValue((string) $value);
        }

        try {
            $this->producer()->send(topic: $topic, value: $payload, key: $key, headers: $kafkaHeaders);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                message: 'Kafka publish failed: ' . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * Создаёт клиент `longlang/phpkafka` Producer.
     *
     * Новый экземпляр на каждый publish — достаточно для portfolio/dev.
     */
    private function producer(): Producer
    {
        $timeoutSeconds = (float) config('kafka.timeout_ms', 5000) / 1000;

        $config = new ProducerConfig([
            'bootstrapServer' => (string) config('kafka.bootstrap_servers'),
            'updateBrokers'   => true,
            'acks'            => (int) config('kafka.acks', -1),
            'connectTimeout'  => 3.0,
            'sendTimeout'     => $timeoutSeconds,
            'recvTimeout'     => $timeoutSeconds,
        ]);

        return new Producer($config);
    }
}
