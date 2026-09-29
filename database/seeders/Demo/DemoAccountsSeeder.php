<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Демо: счета с итоговыми балансами (minor units), согласованными с ledger в {@see DemoTransactionsSeeder}.
 *
 * Требует предварительного {@see DemoCustomersSeeder}.
 */
final class DemoAccountsSeeder extends Seeder
{
    /**
     * Создать все демо-счета (в т.ч. заблокированный RUB с балансом 0).
     */
    public function run(): void
    {
        $this->createAccount(DemoEmails::ALICE, 'RUB', 155_000, AccountStatus::Active);
        $this->createAccount(DemoEmails::ALICE, 'USD', 5_000, AccountStatus::Active);
        $this->createAccount(DemoEmails::BOB, 'RUB', 45_000, AccountStatus::Active);
        $this->createAccount(DemoEmails::ACME, 'RUB', 780_000, AccountStatus::Active);
        $this->createAccount(DemoEmails::ACME, 'USD', 120_000, AccountStatus::Active);
        $this->createAccount(DemoEmails::BLOCKED, 'RUB', 0, AccountStatus::Blocked);
    }

    /**
     * @param non-empty-string $customerEmail ключ {@see DemoEmails}
     * @param non-empty-string $currency      ISO 4217, например RUB
     * @param int              $balance       minor units (копейки)
     */
    private function createAccount(string $customerEmail, string $currency, int $balance, AccountStatus $status): void
    {
        Account::query()->create([
            'customer_id' => $this->customer($customerEmail)->id,
            'currency'    => $currency,
            'balance'     => $balance,
            'status'      => $status,
        ]);
    }

    /**
     * @param non-empty-string $email
     *
     * @throws RuntimeException если customer ещё не создан предыдущим seeder'ом
     */
    private function customer(string $email): Customer
    {
        $customer = Customer::query()->where('email', $email)->first();

        if ($customer === null) {
            throw new RuntimeException("Демо клиент не найден: {$email}");
        }

        return $customer;
    }
}
