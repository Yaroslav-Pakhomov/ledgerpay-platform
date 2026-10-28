<?php

declare(strict_types=1);

namespace App\Application\ReadModel\Projectors;

use App\Domain\ReadModel\Models\TransactionSummaryReadModel;
use App\Domain\Transaction\Models\Transaction;

/**
 * Проектор сводки по операции в таблицу {@see TransactionSummaryReadModel}.
 *
 * Копирует поля транзакции {@see Transaction} и денормализует UUID счетов
 * и почту клиентов (источник/получатель) для списков и фильтров в интерфейсе.
 *
 * Идемпотентен относительно `transaction_id`: повторный вызов обновляет ту же строку.
 */
final class TransactionSummaryProjector
{
    /**
     * Строит или обновляет проекцию для переданной операции.
     *
     * Для пополнения/списания соответствующие счёт и клиент могут отсутствовать —
     * в проекцию попадают пустые значения.
     *
     * @param  Transaction                 $transaction Агрегат операции из таблицы `transactions`
     * @return TransactionSummaryReadModel Сохранённая строка в `transaction_summary_read_models`
     */
    public function project(Transaction $transaction): TransactionSummaryReadModel
    {
        $transaction->loadMissing([
            'sourceAccount.customer',
            'targetAccount.customer',
        ]);

        return TransactionSummaryReadModel::query()->updateOrCreate(
            [
                'transaction_id' => $transaction->id,
            ],
            [
                'transaction_uuid' => $transaction->uuid,
                'type'             => $transaction->type->value,
                'status'           => $transaction->status->value,
                'amount'           => $transaction->amount,
                'currency'         => $transaction->currency,

                'source_account_uuid' => $transaction->sourceAccount?->uuid,
                'target_account_uuid' => $transaction->targetAccount?->uuid,

                'source_customer_uuid'  => $transaction->sourceAccount?->customer?->uuid,
                'source_customer_email' => $transaction->sourceAccount?->customer?->email,

                'target_customer_uuid'  => $transaction->targetAccount?->customer?->uuid,
                'target_customer_email' => $transaction->targetAccount?->customer?->email,

                'failure_reason'         => $transaction->failure_reason,
                'processed_at'           => $transaction->processed_at,
                'transaction_created_at' => $transaction->created_at,
            ],
        );
    }
}
