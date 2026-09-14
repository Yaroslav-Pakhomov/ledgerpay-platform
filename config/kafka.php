<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Kafka enabled
    |--------------------------------------------------------------------------
    |
    | false — OutboxPublisher только пишет в log (dev/test/CI).
    | true  — дополнительно отправляет сообщение в Kafka/Redpanda.
    |
    */
    'enabled' => (bool) env('KAFKA_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Bootstrap servers
    |--------------------------------------------------------------------------
    |
    | Из app-контейнера Sail: redpanda:9092. С хоста: localhost:19092.
    |
    */
    'bootstrap_servers' => env('KAFKA_BOOTSTRAP_SERVERS', 'localhost:9092'),

    /*
    |--------------------------------------------------------------------------
    | Topic
    |--------------------------------------------------------------------------
    |
    | Единый topic для всех domain events; тип события — в поле event_name.
    |
    */
    'topic' => env('KAFKA_TOPIC', 'ledgerpay.domain-events'),

    /*
    |--------------------------------------------------------------------------
    | Producer acks / timeout
    |--------------------------------------------------------------------------
    |
    | acks: -1 — все in-sync replicas; timeout_ms — таймаут produce в миллисекундах.
    |
    */
    'acks'       => (int) env('KAFKA_ACKS', -1),
    'timeout_ms' => (int) env('KAFKA_TIMEOUT_MS', 5000),

];
