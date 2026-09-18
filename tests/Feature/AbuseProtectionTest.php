<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\TestCase;

final class AbuseProtectionTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    public function test_api_response_contains_security_headers(): void
    {
        $account = Account::factory()->create();
        $this->actingAsCustomerFor($account);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_auth_rate_limit_returns_too_many_requests(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => 'missing@example.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'missing@example.com',
            'password' => 'wrong',
        ]);

        $response->assertTooManyRequests()
            ->assertJsonPath('title', 'Слишком много запросов');
    }

    public function test_transaction_has_idempotency_expiry(): void
    {
        Queue::fake();

        $account = Account::factory()->create();
        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'RUB',
        ], [
            'Idempotency-Key' => 'expiry-test-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();

        $this->assertNotNull($transaction->idempotency_expires_at);
    }

    public function test_prune_expired_idempotency_keys_command(): void
    {
        $account = Account::factory()->create();

        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Completed,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 1000,
            'currency'               => 'RUB',
            'idempotency_key'        => 'expired-key-001',
            'idempotency_expires_at' => now()->subDay(),
            'processed_at'           => now(),
        ]);

        $this->artisan('idempotency:prune-expired')
            ->assertSuccessful();

        $transaction->refresh();

        $this->assertSame(
            'expired-' . $transaction->uuid,
            $transaction->idempotency_key,
        );

        $this->assertNull($transaction->idempotency_expires_at);
    }
}
