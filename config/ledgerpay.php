<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Idempotency TTL
    |--------------------------------------------------------------------------
    |
    | Срок хранения ключа идемпотентности в часах.
    | По истечении completed/failed/cancelled транзакции могут быть очищены
    | командой idempotency:prune-expired.
    |
    */
    'idempotency' => [
        'ttl_hours' => env('LEDGERPAY_IDEMPOTENCY_TTL_HOURS', 24),
    ],

    'queues' => [
        /*
         * Количество shard-очередей для ordering на уровне account.
         *
         * - больше shards → больше параллелизма между разными счетами;
         * - один и тот же routing account всегда попадает в один shard;
         * - внутри одной очереди при balance=simple и одном process порядок FIFO.
         */
        'transaction_shards' => (int) env('LEDGERPAY_TRANSACTION_QUEUE_SHARDS', 16),
    ],

    'pagination' => [
        'default_limit' => (int) env('LEDGERPAY_DEFAULT_CURSOR_LIMIT', 50),
        'max_limit'     => (int) env('LEDGERPAY_MAX_CURSOR_LIMIT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Снимки баланса
    |--------------------------------------------------------------------------
    |
    | После каждых N проводок с момента последнего снимка создаётся новая запись
    | account_balance_snapshots (см. BalanceSnapshotService, ADR-013).
    |
    */
    'snapshots' => [
        'every_ledger_entries' => (int) env('LEDGERPAY_SNAPSHOT_EVERY_LEDGER_ENTRIES', 100),
    ],
];
