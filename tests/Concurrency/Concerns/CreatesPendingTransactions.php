<?php

declare(strict_types=1);

namespace Tests\Concurrency\Concerns;

use App\Application\Transaction\Services\TransactionProcessorService;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;

/**
 * Создаёт {@see Transaction} со статусом {@see TransactionStatus::Pending} для прямого вызова
 * {@see TransactionProcessorService}
 * (без HTTP и без обязательной постановки задания в очередь).
 */
trait CreatesPendingTransactions
{
    /**
     * Пополнение в ожидании обработки — на целевой счёт {@see Account}.
     */
    protected function pendingDeposit(Account $target, int $amount, string $idempotencyKey): Transaction
    {
        return Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => null,
            'target_account_id'      => $target->id,
            'amount'                 => $amount,
            'currency'               => $target->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
        ]);
    }

    /**
     * Списание в ожидании обработки — с исходного счёта {@see Account}.
     */
    protected function pendingWithdrawal(Account $source, int $amount, string $idempotencyKey): Transaction
    {
        return Transaction::query()->create([
            'type'                   => TransactionType::Withdrawal,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => $source->id,
            'target_account_id'      => null,
            'amount'                 => $amount,
            'currency'               => $source->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
        ]);
    }

    /**
     * Перевод в ожидании обработки между двумя счетами (валюта — у счёта-источника).
     */
    protected function pendingTransfer(Account $source, Account $target, int $amount, string $idempotencyKey): Transaction
    {
        return Transaction::query()->create([
            'type'                   => TransactionType::Transfer,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => $source->id,
            'target_account_id'      => $target->id,
            'amount'                 => $amount,
            'currency'               => $source->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
        ]);
    }
}
