<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature-тесты жизнеспособности/готовности и диагностики бэк-офиса.
 *
 * - публичные GET `/api/v1/health/live` и `/api/v1/health/ready` без аутентификации;
 * - сводный статус `warning` при неуспешной транзакции (HTTP 200);
 * - `/backoffice/diagnostics` только для пользователя бэк-офиса ({@see User::isBackOffice()});
 * - Artisan `diagnostics:run`.
 */
final class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health/live')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_readiness_endpoint_returns_checks(): void
    {
        $response = $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checked_at',
                'checks' => [
                    '*' => [
                        'name',
                        'status',
                        'message',
                        'context',
                    ],
                ],
            ]);

        $this->assertCount(5, $response->json('checks'));
    }

    public function test_readiness_endpoint_returns_503_when_failed(): void
    {
        DB::shouldReceive('selectOne')
            ->with('SELECT 1 as ok')
            ->andThrow(new RuntimeException('database unavailable'));

        $this->getJson('/api/v1/health/ready')
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'failed');
    }

    public function test_failed_transaction_changes_readiness_to_warning(): void
    {
        $account = Account::factory()->create();

        Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Failed,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 1000,
            'currency'               => 'RUB',
            'idempotency_key'        => 'diagnostics-failed-001',
            'idempotency_expires_at' => now()->addDay(),
            'failure_reason'         => 'demo failure',
        ]);

        $this->getJson('/api/v1/health/ready')->assertOk()->assertJsonPath('status', 'warning');
    }

    public function test_backoffice_can_view_diagnostics_page(): void
    {
        $backoffice = User::factory()->backOffice()->create();

        $this->actingAs($backoffice);

        $this->get('/backoffice/diagnostics')->assertOk();
    }

    public function test_customer_cannot_view_diagnostics_page(): void
    {
        $account = Account::factory()->create();

        $user = User::factory()->forCustomer($account->customer)->create();

        $this->actingAs($user);

        $this->get('/backoffice/diagnostics')->assertForbidden();
    }

    public function test_diagnostics_command_runs(): void
    {
        $this->runArtisanSuccessfully('diagnostics:run');
    }
}
