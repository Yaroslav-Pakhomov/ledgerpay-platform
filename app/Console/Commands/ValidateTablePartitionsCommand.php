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
 * Команда Artisan: сверка исходной таблицы и теневой перед сменой имён.
 *
 * Вызывает {@see PartitionCutover::validate()}: отсутствующие строки, различия по полям каталога
 * и для `ledger_entries` сумма `amount`. Имя таблицы должно входить в {@see PartitionCatalog::tables()}.
 *
 * Пример: `php artisan partitions:validate audit_logs`
 */
#[Description('Сверяет id и ключевые поля source и shadow.')]
#[Signature('partitions:validate {table : audit_logs, ledger_entries, outbox_messages или reconciliation_reports}')]
final class ValidateTablePartitionsCommand extends Command
{
    /**
     * Сверяет таблицы и сообщает, что строки совпадают.
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
            $cutover->validate($table);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($table . ' совпадает со shadow.');

        return self::SUCCESS;
    }
}
