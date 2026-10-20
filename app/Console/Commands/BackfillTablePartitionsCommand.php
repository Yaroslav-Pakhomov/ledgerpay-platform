<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Partitioning\PartitionCatalog;
use App\Console\Partitioning\PartitionCutover;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Команда Artisan: перенос существующих строк в теневую таблицу.
 *
 * Вызывает {@see PartitionCutover::backfill()}: пакеты по `id`, граница пакета берётся из исходной таблицы.
 * Перед запуском нужна теневая таблица (`partitions:prepare`).
 * Имя таблицы должно входить в {@see PartitionCatalog::tables()}.
 *
 * Пример: `php artisan partitions:backfill audit_logs --batch=5000`
 */
#[Description('Дозаливка в shadow пакетами. Граница id берётся из исходной таблицы.')]
#[Signature('partitions:backfill
    {table : audit_logs, ledger_entries, outbox_messages или reconciliation_reports}
    {--batch=5000 : Размер пакета}')]
final class BackfillTablePartitionsCommand extends Command
{
    /**
     * Переносит строки и печатает следующую команду `partitions:validate`.
     *
     * `--batch` — размер пакета; значения меньше 1 внутри переноса приводятся к 1.
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

        try {
            $cutover->backfill($table, (int) $this->option('batch'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backfill завершён. Дальше: partitions:validate ' . $table);

        return self::SUCCESS;
    }
}
