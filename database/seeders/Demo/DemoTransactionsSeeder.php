<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use Database\Seeders\Demo\Concerns\SeedsDemoTransactions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Seeder;
use Random\RandomException;

/**
 * Демо: сценарий deposit / withdraw / transfer / failed (9 транзакций, 9 проводок ledger).
 *
 * Требует {@see DemoAccountsSeeder}. Счета ищутся по {@see DemoEmails} и валюте.
 */
final class DemoTransactionsSeeder extends Seeder
{
    use SeedsDemoTransactions;

    /**
     * @throws ModelNotFoundException если счёт не найден
     * @throws RandomException
     */
    public function run(): void
    {
        $aliceRub = $this->account(DemoEmails::ALICE, 'RUB');
        $aliceUsd = $this->account(DemoEmails::ALICE, 'USD');
        $bobRub   = $this->account(DemoEmails::BOB, 'RUB');
        $acmeRub  = $this->account(DemoEmails::ACME, 'RUB');
        $acmeUsd  = $this->account(DemoEmails::ACME, 'USD');

        $this->completedDeposit($aliceRub, 150_000, 'demo-alice-rub-deposit-001');
        $this->completedWithdrawal($aliceRub, 25_000, 'demo-alice-rub-withdraw-001');
        $this->completedDeposit($aliceUsd, 5_000, 'demo-alice-usd-deposit-001');
        $this->completedDeposit($bobRub, 75_000, 'demo-bob-rub-deposit-001');
        $this->completedTransfer($bobRub, $aliceRub, 30_000, 'demo-bob-to-alice-transfer-001');
        $this->completedDeposit($acmeRub, 900_000, 'demo-acme-rub-deposit-001');
        $this->completedWithdrawal($acmeRub, 120_000, 'demo-acme-rub-withdraw-001');
        $this->completedDeposit($acmeUsd, 120_000, 'demo-acme-usd-deposit-001');
        $this->failedWithdrawal($bobRub, 100_000, 'demo-bob-insufficient-001', 'Недостаточно средств.');
    }

    /**
     * @param non-empty-string $customerEmail
     * @param non-empty-string $currency
     *
     * @throws ModelNotFoundException
     */
    private function account(string $customerEmail, string $currency): Account
    {
        $customer = Customer::query()->where('email', $customerEmail)->firstOrFail();

        return Account::query()
            ->where('customer_id', $customer->id)
            ->where('currency', $currency)
            ->firstOrFail();
    }
}
