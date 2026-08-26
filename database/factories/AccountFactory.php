<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test Factory для агрегата Account.
 *
 * Account — центральный агрегат money-flow: хранит баланс в minor units
 * и принадлежит Customer. Factory создает связанные агрегаты через
 * lazy relation {@see Customer::factory()}, не нарушая границ aggregate root.
 *
 * @extends Factory<Account>
 */
final class AccountFactory extends Factory
{
    #[\Override]
    protected $model = Account::class;

    /**
     * Базовое состояние: активный счет с нулевым балансом в USD.
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'currency'    => 'USD',
            'balance'     => 0,
            'status'      => AccountStatus::Active,
        ];
    }

    /**
     * Задает начальный баланс агрегата в minor units (копейки/центы).
     */
    public function withBalance(int $balance): self
    {
        return $this->state([
            'balance' => $balance,
        ]);
    }

    /**
     * Задает валюту счета — должна совпадать с валютой транзакции.
     */
    public function currency(string $currency): self
    {
        return $this->state([
            'currency' => strtoupper($currency),
        ]);
    }

    /**
     * Состояние для сценариев с заблокированным счетом.
     *
     * Доменный инвариант Account запрещает credit/debit для неактивных счетов.
     */
    public function blocked(): self
    {
        return $this->state([
            'status' => AccountStatus::Blocked,
        ]);
    }
}
