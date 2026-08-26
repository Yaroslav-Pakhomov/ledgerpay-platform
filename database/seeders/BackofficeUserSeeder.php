<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class BackofficeUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@ledgerpay.test'],
            [
                'customer_id' => null,
                'name'        => 'Backoffice Admin',
                'password'    => 'StrongPassword123!',
            ],
        );
    }
}
