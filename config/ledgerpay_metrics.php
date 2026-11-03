<?php

declare(strict_types=1);
use App\Application\Metrics\MetricsSnapshot;
use App\Http\Middleware\AuthorizeMetrics;

/**
 * Настройки операционных метрик LedgerPay (снимок и доступ к /metrics).
 *
 * cache_store — хранилище кэша для {@see MetricsSnapshot}
 * refresh_seconds — ожидаемый интервал обновления (контракт мониторинга; планировщик отдельно)
 * max_snapshot_age_seconds — порог устаревшего снимка для UI и оповещений
 * token — секрет Bearer для {@see AuthorizeMetrics}
 * grafana_url — необязательная ссылка в бэк-офисе (LEDGERPAY_GRAFANA_URL)
 */
return [
    'cache_store' => env('LEDGERPAY_METRICS_CACHE_STORE', 'redis'),

    'refresh_seconds' => 60,

    'max_snapshot_age_seconds' => 180,

    /*
     * Дополнительно: закрытая сеть / ACL прокси.
     * Не передавать секрет в URL.
     */
    'token' => env('LEDGERPAY_METRICS_TOKEN'),

    'grafana_url' => env('LEDGERPAY_GRAFANA_URL'),
];
