<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Запускает Artisan-команду
// withoutOverlapping() — предотвращает одновременное выполнение нескольких экземпляров этой scheduled-команды.
// everyMinute() — планирует запуск раз в минуту;
// «Исходящие» в очередь
Schedule::command('outbox:dispatch-pending --limit=100')->everyMinute()->withoutOverlapping();

// hourly() — планирует запуск раз в час;
// Сверка балансов счетов с реестром проводок
Schedule::command('reconciliation:run --limit=1000')->hourly()->withoutOverlapping();

// daily() - каждый день
// Удаление истекших ключей идемпотентности
Schedule::command('idempotency:prune-expired --limit=1000')->daily()->withoutOverlapping();

// --months=2 у ensure — это текущий месяц и следующий
Schedule::command('partitions:ensure ledger_entries --months=2')->daily()->withoutOverlapping();
Schedule::command('partitions:ensure audit_logs --months=2')->daily()->withoutOverlapping();
Schedule::command('partitions:ensure outbox_messages --months=2')->daily()->withoutOverlapping();
Schedule::command('partitions:ensure reconciliation_reports --months=2')->daily()->withoutOverlapping();

// Снимок проводок делать каждый час
Schedule::command('ledger:snapshot-balances --limit=1000')->hourly()->withoutOverlapping();
