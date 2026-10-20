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
 * Команда Artisan: смена имён таблиц после остановки записи.
 *
 * Вызывает {@see PartitionCutover::swap()}: исходная таблица становится `{table}_legacy`,
 * теневая получает имя `{table}`. Без `--confirm-writes-stopped` команда не выполняется.
 * Имя таблицы должно входить в {@see PartitionCatalog::tables()}.
 *
 * Пример: `php artisan partitions:swap audit_logs --confirm-writes-stopped`
 */
#[Description('Короткое переключение имени таблицы после остановки записи.')]
#[Signature('partitions:swap
    {table : audit_logs, ledger_entries, outbox_messages или reconciliation_reports}
    {--confirm-writes-stopped : Подтверждение, что INSERT в таблицу остановлен}')]
final class SwapTablePartitionsCommand extends Command
{
    /**
     * Переключает имена и напоминает, что `{table}_legacy` остаётся до ручной проверки.
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

        if (!$this->option('confirm-writes-stopped')) {
            $this->error('Сначала artisan down (или REVOKE INSERT), затем повторите с --confirm-writes-stopped.');

            return self::FAILURE;
        }

        try {
            $cutover->swap($table);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($table . ' переключена. ' . $table . '_legacy сохранена до ручной проверки.');
        $this->line('Откат, пока legacy на месте: обратный rename в одной транзакции и вернуть sequence OWNED BY.');

        return self::SUCCESS;
    }
}
