<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Concerns;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Testing\TestResponse;

trait CreatesApiFixtures
{
    protected function createCustomer(
        string $name = 'Alice Morgan',
        string $email = 'alice@example.com',
        CustomerStatus $status = CustomerStatus::Active,
    ): Customer {
        return Customer::query()->create([
            'name'   => $name,
            'email'  => $email,
            'status' => $status,
        ]);
    }

    protected function createAccount(
        Customer $customer,
        string $currency = 'USD',
        int $balance = 0,
        AccountStatus $status = AccountStatus::Active,
    ): Account {
        return Account::query()->create([
            'customer_id' => $customer->id,
            'currency'    => strtoupper($currency),
            'balance'     => $balance,
            'status'      => $status,
        ]);
    }

    protected function idempotencyHeaders(string $key): array
    {
        return ['Idempotency-Key' => $key];
    }

    protected function depositToAccount(
        Account $account,
        int $amount,
        ?string $idempotencyKey = null,
    ): TestResponse {
        return $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => $amount,
            'currency'            => $account->currency,
        ], $this->idempotencyHeaders($idempotencyKey ?? uniqid('deposit-', true)));
    }

    protected function actingAsCustomerFor(Account $account): User
    {
        $user = User::factory()->forCustomer(
            Customer::query()->findOrFail($account->customer_id)
        )->create();

        $this->actingAs($user, 'sanctum');

        return $user;
    }

    protected function actingAsBackoffice(): User
    {
        $user = User::factory()->backoffice()->create();

        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
