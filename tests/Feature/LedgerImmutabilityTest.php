<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Feature-тесты доменного инварианта immutable ledger.
 *
 * LedgerEntry — append-only audit trail движения средств.
 * Доменная модель запрещает update/delete проводок, чтобы
 * сохранить целостность финансового журнала (double-entry accounting).
 */
final class LedgerImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_entry_cannot_be_updated(): void
    {
        $account = Account::factory()
            ->withBalance(10_000)
            ->create();

        $transaction = Transaction::factory()->create([
            'type'              => TransactionType::Deposit,
            'target_account_id' => $account->id,
            'source_account_id' => null,
        ]);

        $entry = LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => 10_000,
            'currency'       => 'USD',
            'balance_after'  => 10_000,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Записи в бухгалтерской книге являются неизменяемыми.');

        $entry->update([
            'amount' => 20_000,
        ]);
    }

    public function test_ledger_entry_cannot_be_deleted(): void
    {
        $account = Account::factory()
            ->withBalance(10_000)
            ->create();

        $transaction = Transaction::factory()->create([
            'type'              => TransactionType::Deposit,
            'target_account_id' => $account->id,
            'source_account_id' => null,
        ]);

        $entry = LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => 10_000,
            'currency'       => 'USD',
            'balance_after'  => 10_000,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Записи в бухгалтерской книге являются неизменяемыми.');

        $entry->delete();
    }
}
