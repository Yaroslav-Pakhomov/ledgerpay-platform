<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan-команда: очистка истёкших ключей идемпотентности.
 *
 * Для завершённых транзакций (completed/failed/cancelled) с истёкшим
 * {@see Transaction::$idempotency_expires_at} заменяет ключ на `expired-{uuid}`,
 * сохраняя ограничение уникальности и след для расследований.
 *
 * Планируется в {@see routes/console.php} ежедневно без параллельного запуска (withoutOverlapping()).
 */
#[Signature('idempotency:prune-expired {--limit=1000}')]
#[Description('Удаление ключей идемпотентности с истёкшим сроком действия из завершённых или неудачных транзакций.')]
final class PruneExpiredIdempotencyKeysCommand extends Command
{
    /**
     * Выбирает истёкшие ключи и заменяет их на expired-{uuid}.
     *
     * @return int код Command::SUCCESS
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $ids = DB::table('transactions')
            ->whereNotNull('idempotency_expires_at')
            ->where('idempotency_expires_at', '<', now())
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('Ключи идемпотентности с истёкшим сроком действия не найдены.');

            return self::SUCCESS;
        }

        DB::table('transactions')
            ->whereIn('id', $ids)
            ->update([
                'idempotency_key'        => DB::raw("'expired-' || uuid::text"),
                'idempotency_expires_at' => null,
                'updated_at'             => now(),
            ]);

        $this->info("Очищено {$ids->count()} ключ(ей) идемпотентности с истёкшим сроком действия.");

        return self::SUCCESS;
    }
}
