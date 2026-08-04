<?php

namespace Database\Seeders;

use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $customer = Customer::query()
            ->updateOrCreate(
                ['email' => 'ivan@mail.ru'],
                [
                    'name'   => 'Иван Иванов',
                    'status' => CustomerStatus::Active,
                ],
            );

        User::query()
            ->updateOrCreate(
                ['email' => 'ivan@mail.ru'],
                [
                    'name'        => 'Иван Иванов',
                    'password'    => 'Q123456123456q_',
                    'customer_id' => $customer->id,
                ],
            );
        $this->command->info('Клиент приложения заполнен!');

        $this->call([
            BackofficeUserSeeder::class,
        ]);
        $this->command->info('Пользователь бэк-офиса заполнен!');
    }
}
