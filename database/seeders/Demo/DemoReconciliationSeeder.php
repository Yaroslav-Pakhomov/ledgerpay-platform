<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Application\Reconciliation\Services\ReconciliationService;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Демо: сверка всех счетов после ledger (ожидается статус matched при корректных балансах seed).
 */
final class DemoReconciliationSeeder extends Seeder
{
    /**
     * @throws Throwable при ошибке записи отчётов в БД
     */
    public function run(): void
    {
        app(ReconciliationService::class)->checkAllAccounts();
    }
}
