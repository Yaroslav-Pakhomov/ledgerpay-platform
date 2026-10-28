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
 * Команда Artisan: подготовка теневой таблицы перед переносом строк.
 *
 * Вызывает {@see PartitionCutover::prepare()}: родительская таблица `{table}_partitioned`,
 * месячные партиции, значение по умолчанию `nextval` для `id`, индексы и триггер копирования вставок.
 * Имя таблицы должно входить в {@see PartitionCatalog::tables()}.
 *
 * Пример: `php artisan partitions:prepare audit_logs --from=2026-05 --months=3`
 */
#[Description('Shadow-таблица, партиции, DEFAULT nextval и копирующий INSERT-триггер.')]
#[Signature('partitions:prepare
    {table : audit_logs, ledger_entries, outbox_messages или reconciliation_reports}
    {--from= : Первый месяц YYYY-MM}
    {--months=3 : Сколько месяцев вперёд от текущего}')]
final class PrepareTablePartitionsCommand extends Command
{
    /**
     * Готовит теневую таблицу и печатает следующую команду `partitions:backfill`.
     *
     * `--from` — первый месяц (`Y-m`); без опции начало берёт {@see PartitionCutover::prepare()}.
     * `--months` — сколько месяцев вперёд от текущего покрыть партициями.
     *
     * @return int Command::SUCCESS|Command::FAILURE
     */
    public function handle(PartitionCutover $cutover, PartitionCatalog $catalog): int
    {
        $table = $this->argument('table');

        if (!is_string($table)) {
            $this->error('Имя таблицы должно быть строкой.');

            return self::FAILURE;
        }

        if (!$catalog->knows($table)) {
            $this->error('Допустимо: ' . implode(', ', $catalog->tables()));

            return self::FAILURE;
        }

        $fromOption = $this->option('from');
        $from       = is_string($fromOption) && $fromOption !== '' ? $fromOption : null;

        try {
            $cutover->prepare($table, $from, (int) $this->option('months'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Shadow для ' . $table . ' готова. Дальше: partitions:backfill ' . $table);

        return self::SUCCESS;
    }
}
