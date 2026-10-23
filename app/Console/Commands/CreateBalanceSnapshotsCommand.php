<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ledger\Snapshots\Services\BalanceSnapshotService;
use App\Domain\Account\Models\Account;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Пакетное создание снимков баланса (`ledger:snapshot-balances`).
 *
 * `--force` — снимок по последней проводке без порога N.
 * Планировщик: hourly в `routes/console.php`.
 */
#[Description('Создать снимки балансов для счетов.')]
#[Signature('ledger:snapshot-balances {--limit=1000} {--force}')]
final class CreateBalanceSnapshotsCommand extends Command
{
    public function handle(BalanceSnapshotService $snapshotService): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $created = 0;

        Account::query()
            ->orderBy('id')
            ->limit($limit)
            ->each(function (Account $account) use ($snapshotService, $force, &$created): void {
                $latestEntry = $account->ledgerEntries()->latest('id')->first();

                if ($latestEntry === null) {
                    return;
                }

                if ($force) {
                    $snapshotService->createForAccount(
                        account: $account,
                        ledgerEntry: $latestEntry,
                        metadata: [
                            'strategy' => 'manual_force',
                        ],
                    );

                    $created++;

                    return;
                }

                $snapshot = $snapshotService->createIfNeeded($account, $latestEntry);

                if ($snapshot !== null) {
                    $created++;
                }
            });

        $this->info("Создано снимков баланса: {$created}.");

        return self::SUCCESS;
    }
}
