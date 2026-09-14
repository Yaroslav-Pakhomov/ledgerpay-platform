<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use longlang\phpkafka\Consumer\Consumer;
use longlang\phpkafka\Consumer\ConsumerConfig;
use Throwable;

/**
 * Demo consumer для локального просмотра domain events из Kafka topic.
 *
 * Не для production — только portfolio smoke test (`make kafka-demo`).
 * Требует `KAFKA_ENABLED=true` и запущенный Redpanda ({@see config/kafka.php}).
 */
#[Signature('kafka:consume-demo {--max=10 : Максимум сообщений до выхода}')]
#[Description('Demo: читать domain events из Kafka topic.')]
final class ConsumeDomainEventsDemoCommand extends Command
{
    /**
     * Читает до `--max` сообщений из topic и выводит key/value в консоль.
     *
     * @return int Command::SUCCESS или Command::FAILURE
     */
    public function handle(): int
    {
        if (!config('kafka.enabled')) {
            $this->error('KAFKA_ENABLED=false. Set KAFKA_ENABLED=true in .env');

            return self::FAILURE;
        }

        $config = new ConsumerConfig([
            'bootstrapServer' => (string) config('kafka.bootstrap_servers'),
            'topic'           => (string) config('kafka.topic'),
            'groupId'         => 'ledgerpay-demo-consumer',
            'autoCommit'      => true,
        ]);

        $consumer = new Consumer($config);
        $max      = (int) $this->option('max');
        $count    = 0;

        $this->info('Listening on topic: ' . config('kafka.topic'));
        $this->info('Press Ctrl+C to stop.');

        while ($count < $max) {
            try {
                $message = $consumer->consume();

                if ($message === null) {
                    continue;
                }

                $count++;

                $this->line(str_repeat('-', 60));
                $this->info("Message #{$count}");
                $this->line('Key: ' . $message->getKey());
                $this->line('Value: ' . $message->getValue());
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
