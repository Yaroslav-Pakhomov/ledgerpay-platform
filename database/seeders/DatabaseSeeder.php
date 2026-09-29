<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Throwable;

/**
 * Корневой seeder: admin бэк-офиса + полный набор демо-данных.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * @throws Throwable
     */
    public function run(): void
    {
        $this->call([
            BackofficeUserSeeder::class,
            DemoDataSeeder::class,
        ]);
        $this->command->info('Пользователь бэк-офиса заполнен!');
        $this->command->info('Демо-данные заполнены!');
    }
}
