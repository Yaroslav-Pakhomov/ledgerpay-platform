<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\TestCase;

final class ApiErrorHandlingTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    public function test_validation_errors_are_returned_as_problem_details(): void
    {
        Queue::fake();

        $response = $this->actingAs(User::factory()->create())->postJson('/api/transactions/deposit', [], [
            'X-Request-Id' => 'test-request-id-001',
        ]);

        $response->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('X-Request-Id', 'test-request-id-001')
            ->assertJsonPath('title', 'Validation failed')
            ->assertJsonPath('status', 422)
            ->assertJsonPath('request_id', 'test-request-id-001')
            ->assertJsonStructure([
                'type',
                'title',
                'status',
                'detail',
                'instance',
                'request_id',
                'errors',
            ]);
    }

    public function test_not_found_errors_are_returned_as_problem_details(): void
    {
        $response = $this->actingAs(User::factory()->create())->getJson('/api/accounts/00000000-0000-0000-0000-000000000000', [
            'X-Request-Id' => 'test-request-id-404',
        ]);

        $response->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('X-Request-Id', 'test-request-id-404')
            ->assertJsonPath('title', 'Resource not found')
            ->assertJsonPath('status', 404)
            ->assertJsonPath('request_id', 'test-request-id-404');
    }

    public function test_request_id_is_generated_when_header_is_missing(): void
    {
        $account = Account::factory()->create();
        $this->actingAsCustomerFor($account);

        $response = $this->getJson('/api/accounts/' . $account->uuid);

        $response->assertOk();

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_domain_rule_violations_are_returned_as_problem_details(): void
    {
        $account = $this->createAccount(
            $this->createCustomer(),
            status: AccountStatus::Blocked,
        );

        $response = $this->actingAs(User::factory()->create())->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('deposit-inactive-error-handling'));

        $response->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('title', 'Domain rule violation')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('detail', 'Счет неактивен.');
    }
}
