<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Metrics\MetricsSnapshot;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PrometheusMetricsTest extends TestCase
{
    public function test_metrics_endpoint_is_not_public(): void
    {
        config()->set('ledgerpay_metrics.token', str_repeat('x', 48));

        $this->get('/metrics')->assertForbidden();
    }

    public function test_metrics_endpoint_renders_a_cached_business_metric(): void
    {
        config()->set('ledgerpay_metrics.token', str_repeat('x', 48));
        config()->set('ledgerpay_metrics.cache_store', 'array');

        Cache::store('array')->put(
            MetricsSnapshot::KEY,
            [
                'transactions_completed'     => 7,
                'snapshot_timestamp_seconds' => 1_760_000_000,
            ],
            600
        );

        $this->withHeaders(['Authorization' => 'Bearer ' . str_repeat('x', 48)])->get('/metrics')->assertOk()->assertSeeHtml('ledgerpay_transactions_completed 7');
    }
}
