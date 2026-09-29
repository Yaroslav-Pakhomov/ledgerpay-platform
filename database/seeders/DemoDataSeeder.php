<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAccountsSeeder;
use Database\Seeders\Demo\DemoAuditSeeder;
use Database\Seeders\Demo\DemoCustomersSeeder;
use Database\Seeders\Demo\DemoReconciliationSeeder;
use Database\Seeders\Demo\DemoTransactionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Оркестратор демо-данных: одна DB-транзакция, фиксированный порядок seeder'ов.
 *
 * Не заменяет {@see BackofficeUserSeeder} — admin создаётся в {@see DatabaseSeeder} раньше.
 *
 * Порядок: клиенты → счета → транзакции → аудит → сверка.
 */
final class DemoDataSeeder extends Seeder
{
    /**
     * @throws Throwable откат всей demo-транзакции при ошибке любого вложенного seeder'а
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                DemoCustomersSeeder::class,
                DemoAccountsSeeder::class,
                DemoTransactionsSeeder::class,
                DemoAuditSeeder::class,
                DemoReconciliationSeeder::class,
            ]);
        });
    }
}
