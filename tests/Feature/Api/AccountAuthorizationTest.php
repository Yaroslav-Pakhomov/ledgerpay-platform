<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_own_account(): void
    {
        $customer = Customer::factory()->create();

        $user = User::factory()->forCustomer($customer)->create();

        $account = Account::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/accounts/'.$account->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $account->uuid);
    }

    public function test_customer_cannot_view_foreign_account(): void
    {
        $ownCustomer = Customer::factory()->create();
        $foreignCustomer = Customer::factory()->create();

        $user = User::factory()->forCustomer($ownCustomer)->create();

        $foreignAccount = Account::factory()->create([
            'customer_id' => $foreignCustomer->id,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/accounts/'.$foreignAccount->uuid)
            ->assertForbidden()
            ->assertJsonPath('title', 'Forbidden');
    }

    public function test_backoffice_can_view_any_account(): void
    {
        $backoffice = User::factory()->backoffice()->create();

        $account = Account::factory()->create();

        $this->actingAs($backoffice, 'sanctum');

        $this->getJson('/api/accounts/'.$account->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $account->uuid);
    }
}
