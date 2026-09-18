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
];
