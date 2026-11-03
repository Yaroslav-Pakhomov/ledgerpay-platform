<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Metrics\MetricsSnapshot;
use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class OperationalMetricsBackofficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_backoffice_can_view_operational_metrics_page(): void
    {
        config()->set('ledgerpay_metrics.cache_store', 'array');

        Cache::store('array')->put(
            MetricsSnapshot::KEY,
            [
                'transactions_completed'     => 3,
                'snapshot_timestamp_seconds' => now()->timestamp,
                'collector_success'          => 1,
            ],
            600,
        );

        $backoffice = User::factory()->backOffice()->create();

        $this->actingAs($backoffice);

        $this->get('/backoffice/operational-metrics')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backoffice/OperationalMetrics')
                ->where('meta.is_empty', false)
                ->where('sections.1.items.2.value', 3)
            );
    }

    public function test_customer_cannot_view_operational_metrics_page(): void
    {
        $account = Account::factory()->create();
        $user    = User::factory()->forCustomer($account->customer)->create();

        $this->actingAs($user);

        $this->get('/backoffice/operational-metrics')->assertForbidden();
    }
}
