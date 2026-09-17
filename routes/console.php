<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule as ScheduleAlias;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// запускает Artisan-команду
// everyMinute() — планирует запуск раз в минуту;
// withoutOverlapping() — предотвращает одновременное выполнение нескольких экземпляров этой scheduled-команды.
ScheduleAlias::command('outbox:dispatch-pending --limit=100')
    ->everyMinute()
    ->withoutOverlapping();

// hourly() — планирует запуск раз в час;
ScheduleAlias::command('reconciliation:run --limit=1000')
    ->hourly()
    ->withoutOverlapping();
