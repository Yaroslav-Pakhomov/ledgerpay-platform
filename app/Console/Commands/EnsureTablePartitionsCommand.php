<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Partitioning\PartitionCatalog;
use App\Console\Partitioning\PartitionCutover;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Команда Artisan: недостающие месячные партиции уже переключённой таблицы.
 *
 * Вызывает {@see PartitionCutover::ensureMonth()} с текущего месяца на заданный горизонт.
 * Таблица должна быть партиционирована и входить в {@see PartitionCatalog::tables()}.
 * На время работы берётся рекомендательная блокировка по имени таблицы.
 *
 * Пример: `php artisan partitions:ensure ledger_entries --months=2`
 */
#[Description('Создаёт недостающие месячные партиции уже переключённой таблицы.')]
#[Signature('partitions:ensure
    {table : ledger_entries, audit_logs, outbox_messages или reconciliation_reports}
    {--months=2 : Текущий месяц и сколько месяцев вперёд}')]
final class EnsureTablePartitionsCommand extends Command
{
    /**
     * Создаёт недостающие партиции и для каждой печатает, создана она или уже есть.
     *
     * `--months` — число месяцев, начиная с текущего; значения меньше 1 приводятся к 1.
     *
     * @return int Command::SUCCESS|Command::FAILURE
     */
    public function handle(PartitionCutover $cutover, PartitionCatalog $catalog): int
    {
        $table = (string) $this->argument('table');

        if (!$catalog->knows($table)) {
            $this->error('Допустимо: ' . implode(', ', $catalog->tables()));

            return self::FAILURE;
        }

        if ($cutover->relationKind($table) !== 'p') {
            $this->error($table . ' ещё не партиционирована.');

            return self::FAILURE;
        }

        DB::statement('SELECT pg_advisory_lock(hashtext(?::text)::bigint)', [$table]);

        try {
            $start  = CarbonImmutable::now()->startOfMonth();
            $months = max(1, (int) $this->option('months'));

            for ($i = 0; $i < $months; $i++) {
                $created = $cutover->ensureMonth($table, $table, $start->addMonths($i));
                $this->line(($created ? 'Создана ' : 'Уже есть ') . $table . '_' . $start->addMonths($i)->format('Y_m'));
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::statement('SELECT pg_advisory_unlock(hashtext(?::text)::bigint)', [$table]);
        }

        return self::SUCCESS;
    }
}
