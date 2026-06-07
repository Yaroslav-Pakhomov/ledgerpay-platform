<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_account_for_active_customer(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Alice Morgan',
            'email' => 'alice@example.com',
            'status' => CustomerStatus::Active,
        ]);

        $response = $this->postJson('/api/accounts', [
            'customer_uuid' => $customer->uuid,
            'currency' => 'rub',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.customer_uuid', $customer->uuid)
            ->assertJsonPath('data.currency', 'RUB')
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.status', 'active');

        $this->assertNotEmpty($response->json('data.uuid'));

        $this->assertDatabaseHas('accounts', [
            'customer_id' => $customer->id,
            'currency' => 'RUB',
            'balance' => 0,
            'status' => AccountStatus::Active->value,
        ]);
    }

    public function test_cannot_create_account_for_blocked_customer(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'status' => CustomerStatus::Blocked,
        ]);

        $response = $this->postJson('/api/accounts', [
            'customer_uuid' => $customer->uuid,
            'currency' => 'RUB',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot open account for inactive customer.');
    }

    public function test_can_show_account_by_uuid(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Alice Morgan',
            'email' => 'alice@example.com',
            'status' => CustomerStatus::Active,
        ]);

        $createResponse = $this->postJson('/api/accounts', [
            'customer_uuid' => $customer->uuid,
            'currency' => 'USD',
        ]);

        $accountUuid = $createResponse->json('data.uuid');

        $response = $this->getJson('/api/accounts/'.$accountUuid);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $accountUuid)
            ->assertJsonPath('data.currency', 'USD');
    }
}
