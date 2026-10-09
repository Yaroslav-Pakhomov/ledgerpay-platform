<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Concerns;

use App\Application\Outbox\Services\OutboxWriterService;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Events\TransactionCompleted;
use App\Domain\Transaction\Events\TransactionFailed;
use App\Domain\Transaction\Models\Transaction;

/**
 * Запись демо-транзакций в терминальном статусе без {@see TransactionProcessorService} и очереди.
 *
 * Для каждой completed-операции: Transaction + LedgerEntry + outbox ({@see TransactionCompleted}).
 * Failed withdrawal — только Transaction + {@see TransactionFailed}, без ledger.
 *
 * `balance_after` в ledger — итоговый snapshot {@see Account::$balance}, не пошаговый running balance.
 * Суммы — minor units (копейки).
 */
trait SeedsDemoTransactions
{
    /** Демо-deposit: credit в ledger + outbox completed. */
    private function completedDeposit(Account $account, int $amount, string $idempotencyKey): Transaction
    {
        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Completed,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => $amount,
            'currency'               => $account->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
            'processed_at'           => now()->subMinutes(random_int(10, 600)),
        ]);

        LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => $amount,
            'currency'       => $account->currency,
            'balance_after'  => $account->balance,
        ]);

        app(OutboxWriterService::class)->recordEvent(
            new TransactionCompleted($transaction)
        );

        return $transaction;
    }

    /** Демо-withdrawal: debit в ledger + outbox completed. */
    private function completedWithdrawal(Account $account, int $amount, string $idempotencyKey): Transaction
    {
        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Withdrawal,
            'status'                 => TransactionStatus::Completed,
            'source_account_id'      => $account->id,
            'target_account_id'      => null,
            'amount'                 => $amount,
            'currency'               => $account->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
            'processed_at'           => now()->subMinutes(random_int(10, 600)),
        ]);

        LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Debit,
            'amount'         => $amount,
            'currency'       => $account->currency,
            'balance_after'  => $account->balance,
        ]);

        app(OutboxWriterService::class)->recordEvent(
            new TransactionCompleted($transaction)
        );

        return $transaction;
    }

    /** Демо-transfer: debit/credit + один outbox completed. */
    private function completedTransfer(Account $source, Account $target, int $amount, string $idempotencyKey): Transaction
    {
        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Transfer,
            'status'                 => TransactionStatus::Completed,
            'source_account_id'      => $source->id,
            'target_account_id'      => $target->id,
            'amount'                 => $amount,
            'currency'               => $source->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
            'processed_at'           => now()->subMinutes(random_int(10, 600)),
        ]);

        LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $source->id,
            'direction'      => LedgerDirection::Debit,
            'amount'         => $amount,
            'currency'       => $source->currency,
            'balance_after'  => $source->balance,
        ]);

        LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $target->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => $amount,
            'currency'       => $target->currency,
            'balance_after'  => $target->balance,
        ]);

        app(OutboxWriterService::class)->recordEvent(
            new TransactionCompleted($transaction)
        );

        return $transaction;
    }

    /** Демо failed withdrawal: outbox failed, ledger не создаётся. */
    private function failedWithdrawal(Account $account, int $amount, string $idempotencyKey, string $reason): Transaction
    {
        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Withdrawal,
            'status'                 => TransactionStatus::Failed,
            'source_account_id'      => $account->id,
            'target_account_id'      => null,
            'amount'                 => $amount,
            'currency'               => $account->currency,
            'idempotency_key'        => $idempotencyKey,
            'idempotency_expires_at' => now()->addDay(),
            'failure_reason'         => $reason,
        ]);

        app(OutboxWriterService::class)->recordEvent(
            new TransactionFailed(
                transaction: $transaction,
                reason: $reason,
            )
        );

        return $transaction;
    }
}
