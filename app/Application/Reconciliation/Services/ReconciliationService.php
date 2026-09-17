<?php

declare(strict_types=1);

namespace App\Application\Reconciliation\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use App\Domain\Reconciliation\Models\ReconciliationReport;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Сервис приложения сверки балансов счетов с неизменным реестром.
 *
 * Для каждого счёта восстанавливает баланс:
 * `ledger_balance = sum(credits) − sum(debits)` и сравнивает с `accounts.balance`.
 * Результат сохраняется в {@see ReconciliationReport} (только чтение, без автокоррекции).
 */
final class ReconciliationService
{
    /**
     * Сверяет один счёт и создаёт отчёт.
     *
     * @throws Throwable при ошибке записи в БД
     */
    public function checkAccount(Account $account): ReconciliationReport
    {
        /**
         * Баланс реестра восстанавливаем из всех проводок:
         * credit увеличивает баланс, debit уменьшает.
         *
         * Это независимая сверка против accounts.balance.
         */
        $ledgerStats = $account->ledgerEntries()
            ->selectRaw('
                COALESCE(SUM(
                    CASE
                        WHEN direction = ? THEN amount
                        WHEN direction = ? THEN -amount
                        ELSE 0
                    END
                ), 0) as calculated_balance,
                COUNT(*) as entries_count
            ', [
                LedgerDirection::Credit->value,
                LedgerDirection::Debit->value,
            ])
            ->first();

        $ledgerBalance = (int) ($ledgerStats->calculated_balance ?? 0);
        $entriesCount  = (int) ($ledgerStats->entries_count ?? 0);

        $difference = $account->balance - $ledgerBalance;

        $status = $difference === 0
            ? ReconciliationStatus::Matched
            : ReconciliationStatus::Mismatched;

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
                'ledger_entries_count' => $entriesCount,
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
