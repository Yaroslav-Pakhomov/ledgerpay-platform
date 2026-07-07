<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Ledger\Enums\LedgerDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\TestCase;

final class AccountApiTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    public function test_can_create_account_for_active_customer(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        $customer = $this->createCustomer();
        $data = [
            'customer_uuid' => $customer->uuid,
            'currency'      => 'rub',
        ];

        $response = $this->post(route('api.accounts.store'), $data);

        $response
            ->assertCreated()
            ->assertJsonPath('data.customer_uuid', $customer->uuid)
            ->assertJsonPath('data.currency', 'RUB')
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.status', 'active');

        $this->assertNotEmpty($response->json('data.uuid'));

        $this->assertDatabaseHas('accounts', [
            'customer_id' => $customer->id,
            'currency'    => 'RUB',
            'balance'     => 0,
            'status'      => AccountStatus::Active->value,
        ]);
    }

    public function test_cannot_create_account_for_blocked_customer(): void
    {
        $customer = $this->createCustomer(
            name: 'Blocked User',
            email: 'blocked@example.com',
            status: CustomerStatus::Blocked,
        );
        $data = [
            'customer_uuid' => $customer->uuid,
            'currency'      => 'RUB',
        ];

        $response = $this->postJson(route('api.accounts.store'), $data);

        $response->assertConflict()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('title', 'Domain rule violation')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('detail', 'Не удается открыть счет для неактивного клиента.');
    }

    public function test_can_show_account_by_uuid(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        $customer = $this->createCustomer();

        $createResponse = $this->postJson('/api/accounts', [
            'customer_uuid' => $customer->uuid,
            'currency'      => 'USD',
        ]);

        $accountUuid = $createResponse->json('data.uuid');

        $response = $this->getJson('/api/accounts/'.$accountUuid);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $accountUuid)
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_can_list_accounts(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        $customer = $this->createCustomer();
        $account = $this->createAccount($customer, currency: 'USD');

        $response = $this->getJson('/api/accounts');

        $response->assertOk()
            ->assertJsonPath('data.0.uuid', $account->uuid)
            ->assertJsonStructure([
                'data' => [
                    ['uuid', 'currency', 'balance', 'status'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_can_get_account_balance(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        $account = $this->createAccount($this->createCustomer());
        $this->depositToAccount($account, 2500, 'balance-deposit-001');

        $response = $this->getJson('/api/accounts/'.$account->uuid.'/balance');

        $response->assertOk()
            ->assertJsonPath('account_uuid', $account->uuid)
            ->assertJsonPath('balance', 2500)
            ->assertJsonPath('currency', 'USD');
    }

    public function test_can_get_account_ledger_after_deposit(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        $account = $this->createAccount($this->createCustomer());
        $this->depositToAccount($account, 1500, 'ledger-deposit-001');

        $response = $this->getJson('/api/accounts/'.$account->uuid.'/ledger');

        $response->assertOk()
            ->assertJsonPath('data.0.direction', LedgerDirection::Credit->value)
            ->assertJsonPath('data.0.amount', 1500)
            ->assertJsonPath('data.0.account_uuid', $account->uuid)
            ->assertJsonStructure([
                'data' => [
                    ['direction', 'amount', 'currency', 'balance_after'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_returns_404_for_unknown_account(): void
    {
        $response = $this->getJson('/api/accounts/'.Str::uuid()->toString());

        $response->assertNotFound();
    }

    public function test_validates_store_account_request(): void
    {
        $response = $this->postJson('/api/accounts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_uuid', 'currency']);
    }
}
