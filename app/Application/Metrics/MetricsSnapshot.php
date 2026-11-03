<?php

declare(strict_types=1);

namespace App\Application\Metrics;

use App\Console\Commands\RefreshLedgerpayMetrics;
use App\Http\Controllers\Web\Backoffice\OperationalMetricsController;
use App\Providers\PrometheusServiceProvider;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Хранилище предрасчитанных значений метрик для Prometheus и бэк-офиса.
 *
 * Запись: {@see RefreshLedgerpayMetrics}.
 * Чтение: {@see PrometheusServiceProvider} и
 * {@see OperationalMetricsController}.
 */
final class MetricsSnapshot
{
    /** Ключ записи снимка в хранилище кэша. */
    public const string KEY = 'ledgerpay:observability:metrics:v1';

    /**
     * Последний сохранённый снимок.
     *
     * @return array<string, int|float> Пустой массив, если ключ отсутствует
     *
     * @throws InvalidArgumentException
     */
    public function values(): array
    {
        $values = Cache::store(config('ledgerpay_metrics.cache_store', 'redis'))->get(self::KEY, []);

        return is_array($values) ? $values : [];
    }

    /**
     * Сохраняет снимок с TTL 10 минут.
     *
     * @param array<string, int|float> $values Ключи совпадают с именами метрик в PrometheusServiceProvider
     */
    public function save(array $values): void
    {
        Cache::store(config('ledgerpay_metrics.cache_store', 'redis'))->put(self::KEY, $values, now()->addMinutes(10));
    }
}
