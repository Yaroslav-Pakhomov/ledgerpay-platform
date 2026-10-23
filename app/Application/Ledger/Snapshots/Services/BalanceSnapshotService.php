<?php

declare(strict_types=1);

namespace App\Application\Ledger\Snapshots\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Snapshots\Models\AccountBalanceSnapshot;

/**
 * Application Service для снимков баланса счетов.
 *
 * Снимки неизменяемы ({@see AccountBalanceSnapshot}) и служат опорной точкой
 * для поэтапной сверки на длинной истории проводок.
 *
 * Порог автосоздания: `config('ledgerpay.snapshots.every_ledger_entries')`.
 */
final class BalanceSnapshotService
{
    /**
     * Создаёт снимок текущего баланса счёта (опционально привязка к проводке).
     */
    public function createForAccount(Account $account, ?LedgerEntry $ledgerEntry = null, array $metadata = []): AccountBalanceSnapshot
    {
        return AccountBalanceSnapshot::query()->create([
            'account_id'      => $account->id,
            'ledger_entry_id' => $ledgerEntry?->id,
            'balance'         => $account->balance,
            'currency'        => $account->currency,
            'snapshotted_at'  => now(),
            'metadata'        => $metadata === [] ? null : $metadata,
        ]);
    }

    /** Последний снимок счёта по `id` (не по `snapshotted_at`). */
    public function latestForAccount(Account $account): ?AccountBalanceSnapshot
    {
        return AccountBalanceSnapshot::query()->where('account_id', $account->id)->latest('id')->first();
    }

    /** true, если с последнего снимка накопилось не менее N проводок. */
    public function shouldSnapshot(Account $account, int $everyEntries = 100): bool
    {
        $latestSnapshot = $this->latestForAccount($account);

        $query = $account->ledgerEntries();

        if ($latestSnapshot?->ledger_entry_id !== null) {
            $query->where('id', '>', $latestSnapshot->ledger_entry_id);
        }

        return $query->count() >= $everyEntries;
    }

    /** Создаёт снимок, если {@see self::shouldSnapshot()} вернул true. */
    public function createIfNeeded(Account $account, ?LedgerEntry $ledgerEntry = null): ?AccountBalanceSnapshot
    {
        $everyEntries = (int) config('ledgerpay.snapshots.every_ledger_entries', 100);

        if (!$this->shouldSnapshot($account, $everyEntries)) {
            return null;
        }

        return $this->createForAccount(
            account: $account,
            ledgerEntry: $ledgerEntry,
            metadata: [
                'strategy'  => 'every_ledger_entries',
                'threshold' => $everyEntries,
            ],
        );
    }
}
