<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test Factory для агрегата Customer.
 *
 * Не является частью доменного слоя — инфраструктурный helper
 * для feature-тестов и seed-сценариев. Создает валидные экземпляры
 * доменной сущности Customer с корректным lifecycle-статусом.
 *
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Базовое состояние: активный клиент с уникальным email.
     */
    public function definition(): array
    {
        return [
            'name'   => fake()->name(),
            'email'  => fake()->unique()->safeEmail(),
            'status' => CustomerStatus::Active,
        ];
    }

    /**
     * Состояние для сценариев с заблокированным клиентом.
     *
     * Используется при проверке доменных инвариантов,
     * запрещающих операции для неактивных участников.
     */
    public function blocked(): self
    {
        return $this->state([
            'status' => CustomerStatus::Blocked,
        ]);
    }
}
