<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Ledger\Enums\LedgerDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\TestCase;

final class TransactionApiTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    public function test_can_deposit_to_account(): void
    {
        $account = $this->createAccount($this->createCustomer());

        $response = $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('deposit-001'));

        $response->assertOk()
            ->assertJsonPath('data.type', 'deposit')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.target_account_uuid', $account->uuid);

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 1000,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $account->id,
            'direction'  => LedgerDirection::Credit->value,
            'amount'     => 1000,
        ]);
    }

    public function test_deposit_is_idempotent(): void
    {
        $account = $this->createAccount($this->createCustomer());

        $first = $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('deposit-idem-001'));

        $second = $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('deposit-idem-001'));

        $first->assertOk();
        $second->assertOk()
            ->assertJsonPath('data.uuid', $first->json('data.uuid'));

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 1000,
        ]);

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_deposit_requires_idempotency_key(): void
    {
        $account = $this->createAccount($this->createCustomer());

        $response = $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['Idempotency-Key']);
    }

    public function test_deposit_fails_for_inactive_account(): void
    {
        $account = $this->createAccount(
            $this->createCustomer(),
            status: AccountStatus::Blocked,
        );

        $response = $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('deposit-inactive-001'));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Счет неактивен.');

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 0,
        ]);
    }

    public function test_deposit_fails_for_currency_mismatch(): void
    {
        $account = $this->createAccount($this->createCustomer(), currency: 'USD');

        $response = $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'EUR',
        ], $this->idempotencyHeaders('deposit-currency-001'));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Валюта операции не совпадает с валютой счета.');

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 0,
        ]);
    }

    public function test_can_withdraw_from_account(): void
    {
        $account = $this->createAccount($this->createCustomer());
        $this->depositToAccount($account, 5000, 'fund-for-withdraw');

        $response = $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 2000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('withdraw-001'));

        $response->assertOk()
            ->assertJsonPath('data.type', 'withdrawal')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.source_account_uuid', $account->uuid);

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 3000,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $account->id,
            'direction'  => LedgerDirection::Debit->value,
            'amount'     => 2000,
        ]);
    }

    public function test_withdraw_fails_when_insufficient_funds(): void
    {
        $account = $this->createAccount($this->createCustomer(), balance: 100);

        $response = $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 500,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('withdraw-insufficient-001'));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Недостаточно средств.');

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 100,
        ]);
    }

    public function test_withdraw_is_idempotent(): void
    {
        $account = $this->createAccount($this->createCustomer());
        $this->depositToAccount($account, 5000, 'fund-for-withdraw-idem');

        $first = $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('withdraw-idem-001'));

        $second = $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('withdraw-idem-001'));

        $first->assertOk();
        $second->assertOk()
            ->assertJsonPath('data.uuid', $first->json('data.uuid'));

        $this->assertDatabaseHas('accounts', [
            'id'      => $account->id,
            'balance' => 4000,
        ]);
    }

    public function test_can_transfer_between_accounts(): void
    {
        $customer = $this->createCustomer();
        $source = $this->createAccount($customer, currency: 'USD');
        $target = $this->createAccount($customer, currency: 'USD');
        $this->depositToAccount($source, 5000, 'fund-for-transfer');

        $response = $this->postJson('/api/transactions/transfer', [
            'source_account_uuid' => $source->uuid,
            'target_account_uuid' => $target->uuid,
            'amount'              => 1500,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('transfer-001'));

        $response->assertOk()
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.source_account_uuid', $source->uuid)
            ->assertJsonPath('data.target_account_uuid', $target->uuid);

        $this->assertDatabaseHas('accounts', ['id' => $source->id, 'balance' => 3500]);
        $this->assertDatabaseHas('accounts', ['id' => $target->id, 'balance' => 1500]);

        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $source->id,
            'direction'  => LedgerDirection::Debit->value,
            'amount'     => 1500,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'account_id' => $target->id,
            'direction'  => LedgerDirection::Credit->value,
            'amount'     => 1500,
        ]);
    }

    public function test_transfer_fails_when_insufficient_funds(): void
    {
        $customer = $this->createCustomer();
        $source = $this->createAccount($customer);
        $target = $this->createAccount($customer);

        $response = $this->postJson('/api/transactions/transfer', [
            'source_account_uuid' => $source->uuid,
            'target_account_uuid' => $target->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('transfer-insufficient-001'));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failure_reason', 'Недостаточно средств.');

        $this->assertDatabaseHas('accounts', ['id' => $source->id, 'balance' => 0]);
        $this->assertDatabaseHas('accounts', ['id' => $target->id, 'balance' => 0]);
    }

    public function test_transfer_validates_same_account(): void
    {
        $account = $this->createAccount($this->createCustomer());

        $response = $this->postJson('/api/transactions/transfer', [
            'source_account_uuid' => $account->uuid,
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], $this->idempotencyHeaders('transfer-same-001'));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['target_account_uuid']);
    }

    public function test_can_list_transactions(): void
    {
        $account = $this->createAccount($this->createCustomer());
        $this->depositToAccount($account, 1000, 'list-deposit-001');

        $response = $this->getJson('/api/transactions');

        $response->assertOk()
            ->assertJsonPath('data.0.type', 'deposit')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonStructure([
                'data' => [
                    ['uuid', 'type', 'status', 'amount', 'currency'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_can_show_transaction_by_uuid(): void
    {
        $account = $this->createAccount($this->createCustomer());
        $deposit = $this->depositToAccount($account, 1000, 'show-deposit-001');
        $transactionUuid = $deposit->json('data.uuid');

        $response = $this->getJson('/api/transactions/'.$transactionUuid);

        $response->assertOk()
            ->assertJsonPath('uuid', $transactionUuid)
            ->assertJsonPath('type', 'deposit')
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('amount', 1000);
    }

    public function test_returns_404_for_unknown_transaction(): void
    {
        $response = $this->getJson('/api/transactions/'.Str::uuid()->toString());

        $response->assertNotFound();
    }
}
