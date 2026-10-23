<?php

declare(strict_types=1);

namespace App\Application\Reconciliation\Services;

use App\Application\Ledger\Snapshots\Services\BalanceSnapshotService;
use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Snapshots\Models\AccountBalanceSnapshot;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use App\Domain\Reconciliation\Models\ReconciliationReport;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Сервис приложения сверки балансов счетов с неизменным реестром.
 *
 * При наличии снимка: база из последнего {@see AccountBalanceSnapshot}
 * плюс сумма проводок после `ledger_entry_id` снимка.
 * Без снимка — полный пересчёт по всем проводкам счёта.
 *
 * Стратегия фиксируется в metadata отчёта (`full_ledger_scan` / `snapshot_incremental`).
 * Результат — append-only {@see ReconciliationReport} (без автокоррекции баланса).
 */
final readonly class ReconciliationService
{
    public function __construct(
        private BalanceSnapshotService $snapshotService,
    ) {}

    /**
     * Сверяет один счёт и создаёт отчёт.
     *
     * Metadata: `strategy`, `snapshot_uuid`, `ledger_delta`, …
     *
     * @throws Throwable при ошибке записи в БД
     */
    public function checkAccount(Account $account): ReconciliationReport
    {
        $snapshot = $this->snapshotService->latestForAccount($account);

        $ledgerQuery = $account->ledgerEntries();

        $baseBalance       = 0;
        $fromLedgerEntryId = null;

        if ($snapshot !== null) {
            $baseBalance       = $snapshot->balance;
            $fromLedgerEntryId = $snapshot->ledger_entry_id;

            if ($fromLedgerEntryId !== null) {
                $ledgerQuery->where('id', '>', $fromLedgerEntryId);
            }
        }

        /**
         * Delta по проводкам (все или только после снимка):
         * credit увеличивает баланс, debit уменьшает.
         */
        $ledgerDelta = (int) $ledgerQuery
            ->selectRaw('
                COALESCE(SUM(
                    CASE
                        WHEN direction = ? THEN amount
                        WHEN direction = ? THEN -amount
                        ELSE 0
                    END
                ), 0) as calculated_delta
            ', [
                LedgerDirection::Credit->value,
                LedgerDirection::Debit->value,
            ])
            ->value('calculated_delta');

        $ledgerBalance = $baseBalance + $ledgerDelta;
        $difference    = $account->balance - $ledgerBalance;

        $status = $difference === 0 ? ReconciliationStatus::Matched : ReconciliationStatus::Mismatched;

        return ReconciliationReport::query()->create([
            'account_id'      => $account->id,
            'account_balance' => $account->balance,
            'ledger_balance'  => $ledgerBalance,
            'difference'      => $difference,
            'currency'        => $account->currency,
            'status'          => $status,
            'checked_at'      => now(),
            'metadata'        => [
                'account_uuid'         => $account->uuid,
                'strategy'             => $snapshot === null ? 'full_ledger_scan' : 'snapshot_incremental',
                'snapshot_uuid'        => $snapshot?->uuid,
                'snapshot_balance'     => $snapshot?->balance,
                'from_ledger_entry_id' => $fromLedgerEntryId,
                'ledger_delta'         => $ledgerDelta,
            ],
        ]);
    }

    /**
     * Сверяет партию счетов (по умолчанию до 1000).
     *
     * @return Collection<int, ReconciliationReport>
     *
     * @throws Throwable
     */
    public function checkAllAccounts(int $limit = 1000): Collection
    {
        return Account::query()
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Account $account) => $this->checkAccount($account));
    }
}
