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
 * Команда Artisan: подготовка, перенос строк и проверка без смены имён.
 *
 * По очереди вызывает {@see PartitionCutover::prepare()}, {@see PartitionCutover::backfill()}
 * и {@see PartitionCutover::validate()}. Смену имён выполняет отдельно `partitions:swap`.
 * Если таблица уже партиционирована, сразу завершается успешно.
 * Имя таблицы должно входить в {@see PartitionCatalog::tables()}.
 *
 * Пример: `php artisan partitions:cutover ledger_entries --from=2026-05 --months=3 --batch=5000`
 */
#[Description('prepare, backfill и validate для одной таблицы. Swap не выполняет.')]
#[Signature('partitions:cutover
    {table : ledger_entries, audit_logs, outbox_messages или reconciliation_reports}
    {--from= : Первый месяц YYYY-MM; без опции — месяц MIN(created_at)}
    {--months=3 : Сколько месяцев вперёд от текущего покрыть сверх данных}
    {--batch=5000 : Размер пакета backfill}')]
final class CutoverTablePartitionsCommand extends Command
{
    /**
     * Проводит три стадии и печатает команду смены имён.
     *
     * `--from` — первый месяц (`Y-m`); без опции начало берёт подготовка.
     * `--months` — сколько месяцев вперёд от текущего покрыть партициями.
     * `--batch` — размер пакета переноса.
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

        if ($cutover->relationKind($table) === 'p') {
            $this->info($table . ' уже партиционирована. Дальше: partitions:ensure ' . $table);

            return self::SUCCESS;
        }

        $from = $this->option('from') ? (string) $this->option('from') : null;

        try {
            $this->info('prepare');
            $cutover->prepare($table, $from, (int) $this->option('months'));

            $this->info('backfill');
            $cutover->backfill($table, (int) $this->option('batch'));

            $this->info('validate');
            $cutover->validate($table);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($table . ' сверена со shadow. Переключение: partitions:swap ' . $table . ' --confirm-writes-stopped');

        if ($cutover->relationKind($table . '_partitioned') !== 'p') {
            $this->error('Shadow не видна после validate.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
