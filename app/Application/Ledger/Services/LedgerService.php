<?php

declare(strict_types=1);

namespace App\Application\Ledger\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transaction\Models\Transaction;

/**
 * Application Service для работы с бухгалтерской книгой.
 *
 * Отвечает за создание неизменяемых записей (LedgerEntry),
 * отражающих результат выполнения денежных операций.
 *
 * Сервис не изменяет баланс счета и не содержит
 * бизнес-логики обработки транзакций.
 * Его единственная ответственность — зафиксировать факт
 * изменения состояния системы для последующего аудита.
 */
final class LedgerService
{
    /**
     * Создает запись о списании средств со счета.
     */
    public function debit(
        Transaction $transaction,
        Account $account,
        int $amount,
    ): LedgerEntry {
        return LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Debit,
            'amount'         => $amount,
            'currency'       => $account->currency,
            'balance_after'  => $account->balance,
        ]);
    }

    /**
     * Создает запись о зачислении средств на счет.
     */
    public function credit(
        Transaction $transaction,
        Account $account,
        int $amount,
    ): LedgerEntry {
        return LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => $amount,
            'currency'       => $account->currency,
            'balance_after'  => $account->balance,
        ]);
    }
}
