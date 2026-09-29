<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Демо: клиенты и пользователи для входа (3 active, 1 blocked).
 *
 * Счета заблокированного клиента создаёт {@see DemoAccountsSeeder}.
 * Рассчитано на `migrate:fresh --seed` — blocked создаётся через {@see Customer::create()}.
 */
final class DemoCustomersSeeder extends Seeder
{
    /**
     * Заполнить демо-клиентов и пользователей.
     */
    public function run(): void
    {
        $this->createCustomerUser('Alice Morgan', DemoEmails::ALICE);
        $this->createCustomerUser('Bob Carter', DemoEmails::BOB);
        $this->createCustomerUser('Acme Trading LLC', DemoEmails::ACME);

        $blocked = Customer::query()->create([
            'name'   => 'Заблокированный demo-клиент',
            'email'  => DemoEmails::BLOCKED,
            'status' => CustomerStatus::Blocked,
        ]);

        User::query()->create([
            'customer_id' => $blocked->id,
            'name'        => 'Заблокированный demo-клиент',
            'email'       => DemoEmails::BLOCKED,
            'password'    => 'StrongPassword123!',
        ]);
    }

    /**
     * Идемпотентно создать активного клиента и пользователя (пароль demo, см. README).
     *
     * @param non-empty-string $email
     */
    private function createCustomerUser(string $name, string $email): void
    {
        $customer = Customer::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'   => $name,
                'status' => CustomerStatus::Active,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'customer_id' => $customer->id,
                'name'        => $name,
                'password'    => 'StrongPassword123!',
            ],
        );

    }
}
