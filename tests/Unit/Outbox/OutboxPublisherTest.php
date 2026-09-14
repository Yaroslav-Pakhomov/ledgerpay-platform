<?php

declare(strict_types=1);

namespace Tests\Unit\Outbox;

use App\Application\Outbox\Contracts\IKafkaMessageProducer;
use App\Application\Outbox\Services\KafkaMessageProducer;
use App\Application\Outbox\Services\OutboxPublisher;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Expectation;
use Tests\TestCase;

/**
 * Unit-тесты {@see OutboxPublisher}: structured log + опциональная отправка в Kafka.
 *
 * {@see KafkaMessageProducer} — final; mock через {@see IKafkaMessageProducer}.
 * Проверки — Mockery expectations; верификация в {@see tearDown()} → Mockery::close().
 */
final class OutboxPublisherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * При KAFKA_ENABLED=true: log + один вызов IKafkaMessageProducer::publish().
     */
    public function test_publish_logs_and_sends_to_kafka_when_enabled(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('kafka.enabled', true);
        Config::set('kafka.topic', 'ledgerpay.domain-events');

        Log::shouldReceive('info')
            ->once()
            ->with('Outbox message published.', Mockery::type('array'));

        $kafka = Mockery::mock(IKafkaMessageProducer::class);

        /** @var Expectation $expectation */
        $expectation = $kafka->shouldReceive('publish');
        $expectation
            ->once()
            ->with(
                'ledgerpay.domain-events',
                'tx-uuid-1',
                Mockery::type('array'),
                Mockery::type('array'),
            );

        $message = new OutboxMessage([
            'uuid'           => 'outbox-uuid-1',
            'event_name'     => 'transaction.created',
            'aggregate_type' => Transaction::class,
            'aggregate_id'   => 1,
            'aggregate_uuid' => 'tx-uuid-1',
            'payload'        => ['transaction_uuid' => 'tx-uuid-1'],
            'headers'        => ['request_id' => 'req-1'],
            'status'         => OutboxStatus::Pending,
        ]);

        /** @phpstan-ignore-next-line argument.type */
        new OutboxPublisher($kafka)->publish($message);
    }

    /**
     * При KAFKA_ENABLED=false: только log, Kafka transport не вызывается.
     */
    public function test_publish_only_logs_when_kafka_disabled(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('kafka.enabled', false);

        Log::shouldReceive('info')->once();

        $message = new OutboxMessage([
            'uuid'           => 'outbox-uuid-2',
            'event_name'     => 'transaction.completed',
            'aggregate_type' => Transaction::class,
            'aggregate_id'   => 1,
            'aggregate_uuid' => 'tx-uuid-2',
            'payload'        => ['transaction_uuid' => 'tx-uuid-2'],
            'headers'        => [],
            'status'         => OutboxStatus::Pending,
        ]);

        new OutboxPublisher(new KafkaMessageProducer)->publish($message);
    }
}
