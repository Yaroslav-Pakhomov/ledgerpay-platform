<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Демо-интеграция: источник и токен webhook
    |--------------------------------------------------------------------------
    |
    | source — значение колонки inbox_messages.source для событий с
    | POST /api/v1/integrations/inbox/events ({@see InboxWebhookController}).
    |
    | token — ожидаемый секрет в заголовке Authorization: Bearer <token>
    | ({@see AuthenticateInboxProvider}); не короче 32 символов, иначе 403.
    |
    */
    'source' => env('INBOX_DEMO_SOURCE', 'demo-provider'),
    'token'  => env('INBOX_DEMO_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Очередь обработки
    |--------------------------------------------------------------------------
    |
    | Имя Redis-очереди для {@see ProcessInboxMessageJob} и supervisor Horizon.
    |
    */
    'queue' => 'inbox',

    /*
    |--------------------------------------------------------------------------
    | Retry и dispatch
    |--------------------------------------------------------------------------
    |
    | max_attempts — после этой попытки статус dead ({@see InboxProcessorService}).
    | retry_base_seconds / retry_max_seconds — экспоненциальный backoff при failed.
    | dispatch_lease_seconds — аренда при {@see DispatchPendingInboxMessagesCommand},
    |   чтобы не ставить дублирующие jobs на одно сообщение.
    | dispatch_batch_size — лимит за один запуск inbox:dispatch-pending (1..1000).
    |
    */
    'max_attempts'           => 5,
    'retry_base_seconds'     => 30,
    'retry_max_seconds'      => 3600,
    'dispatch_lease_seconds' => 180,
    'dispatch_batch_size'    => 100,

    /*
    |--------------------------------------------------------------------------
    | Лимит размера тела webhook
    |--------------------------------------------------------------------------
    |
    | Сверяется с strlen(raw body) в {@see AuthenticateInboxProvider}; при превышении — 413.
    |
    */
    'max_payload_bytes' => 65536,
];
