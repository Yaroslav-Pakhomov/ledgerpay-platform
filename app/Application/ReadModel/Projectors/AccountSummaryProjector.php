<?php

declare(strict_types=1);

namespace App\Application\ReadModel\Projectors;

use App\Domain\Account\Models\Account;
use App\Domain\ReadModel\Models\AccountSummaryReadModel;

/**
 * Проектор сводки по счёту в таблицу {@see AccountSummaryReadModel}.
 *
 * Переносит актуальные поля счёта {@see Account} и связанного клиента
 * в денормализованную строку для списков в интерфейсе без соединений с другими таблицами.
 * Дополнительно пересчитывает число проводок и время последней записи реестра.
 *
 * Идемпотентен относительно `account_id`: повторный вызов обновляет ту же строку.
 */
final class AccountSummaryProjector
{
    /**
     * Строит или обновляет проекцию для переданного счёта.
     *
     * Требует существующего клиента у счёта (подгружается через `customer`).
     * Подсчёт проводок выполняется запросом к {@see Account::ledgerEntries()}.
     *
     * @param  Account                 $account Агрегат счёта из таблицы `accounts`
     * @return AccountSummaryReadModel Сохранённая строка в `account_summary_read_models`
     */
    public function project(Account $account): AccountSummaryReadModel
    {
        $account->loadMissing('customer');

        $lastLedgerEntry = $account->ledgerEntries()->latest('id')->first();

        return AccountSummaryReadModel::query()->updateOrCreate(
            [
                'account_id' => $account->id,
            ],
            [
                'account_uuid'         => $account->uuid,
                'customer_id'          => $account->customer_id,
                'customer_uuid'        => $account->customer->uuid,
                'customer_name'        => $account->customer->name,
                'customer_email'       => $account->customer->email,
                'currency'             => $account->currency,
                'balance'              => $account->balance,
                'status'               => $account->status->value,
                'ledger_entries_count' => $account->ledgerEntries()->count(),
                'last_ledger_entry_at' => $lastLedgerEntry?->created_at,
            ],
        );
    }
}
