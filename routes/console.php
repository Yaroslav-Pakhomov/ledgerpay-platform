<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleAlias;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Запускает Artisan-команду
// withoutOverlapping() — предотвращает одновременное выполнение нескольких экземпляров этой scheduled-команды.
// everyMinute() — планирует запуск раз в минуту;
// «Исходящие» в очередь
ScheduleAlias::command('outbox:dispatch-pending --limit=100')
    ->everyMinute()
    ->withoutOverlapping();

// hourly() — планирует запуск раз в час;
// Сверка балансов счетов с реестром проводок
ScheduleAlias::command('reconciliation:run --limit=1000')
    ->hourly()
    ->withoutOverlapping();

// daily() - каждый день
// Удаление истекших ключей идемпотентности
ScheduleAlias::command('idempotency:prune-expired --limit=1000')
    ->daily()
    ->withoutOverlapping();
