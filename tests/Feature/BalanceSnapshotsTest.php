<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Ledger\Snapshots\Services\BalanceSnapshotService;
use App\Application\Reconciliation\Services\ReconciliationService;
use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Snapshots\Models\AccountBalanceSnapshot;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;
use Throwable;

final class BalanceSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_snapshot_can_be_created_for_account(): void
    {
        $account = Account::factory()
            ->withBalance(10_000)
            ->create();

        $snapshot = app(BalanceSnapshotService::class)
            ->createForAccount($account);

        $this->assertSame($account->id, $snapshot->account_id);
        $this->assertSame(10_000, $snapshot->balance);
        $this->assertSame($account->currency, $snapshot->currency);
    }

    public function test_balance_snapshot_is_immutable(): void
    {
        $account = Account::factory()
            ->withBalance(10_000)
            ->create();

        $snapshot = app(BalanceSnapshotService::class)
            ->createForAccount($account);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Снимки баланса являются неизменяемыми.');

        $snapshot->update([
            'balance' => 20_000,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_reconciliation_uses_snapshot_incremental_strategy(): void
    {
        $account = Account::factory()
            ->withBalance(15_000)
            ->create();

        $firstTransaction = Transaction::query()->create([
            'type'              => TransactionType::Deposit,
            'status'            => TransactionStatus::Completed,
            'source_account_id' => null,
            'target_account_id' => $account->id,
            'amount'            => 10_000,
            'currency'          => 'RUB',
            'idempotency_key'   => 'snapshot-recon-001',
            'processed_at'      => now(),
        ]);

        $firstEntry = LedgerEntry::query()->create([
            'transaction_id' => $firstTransaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => 10_000,
            'currency'       => 'RUB',
            'balance_after'  => 10_000,
        ]);

        AccountBalanceSnapshot::query()->create([
            'account_id'      => $account->id,
            'ledger_entry_id' => $firstEntry->id,
            'balance'         => 10_000,
            'currency'        => 'RUB',
            'snapshotted_at'  => now(),
        ]);

        $secondTransaction = Transaction::query()->create([
            'type'              => TransactionType::Deposit,
            'status'            => TransactionStatus::Completed,
            'source_account_id' => null,
            'target_account_id' => $account->id,
            'amount'            => 5_000,
            'currency'          => 'RUB',
            'idempotency_key'   => 'snapshot-recon-002',
            'processed_at'      => now(),
        ]);

        LedgerEntry::query()->create([
            'transaction_id' => $secondTransaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => 5_000,
            'currency'       => 'RUB',
            'balance_after'  => 15_000,
        ]);

        $report = app(ReconciliationService::class)->checkAccount($account);

        $this->assertSame(ReconciliationStatus::Matched, $report->status);
        $this->assertSame(15_000, $report->ledger_balance);
        $this->assertSame('snapshot_incremental', $report->metadata['strategy']);
        $this->assertSame(5_000, $report->metadata['ledger_delta']);
    }

    public function test_snapshot_command_creates_snapshots(): void
    {
        $account = Account::factory()
            ->withBalance(10_000)
            ->create();

        $transaction = Transaction::query()->create([
            'type'              => TransactionType::Deposit,
            'status'            => TransactionStatus::Completed,
            'source_account_id' => null,
            'target_account_id' => $account->id,
            'amount'            => 10_000,
            'currency'          => 'RUB',
            'idempotency_key'   => 'snapshot-command-001',
            'processed_at'      => now(),
        ]);

        LedgerEntry::query()->create([
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => LedgerDirection::Credit,
            'amount'         => 10_000,
            'currency'       => 'RUB',
            'balance_after'  => 10_000,
        ]);

        $this->runArtisanSuccessfully('ledger:snapshot-balances', ['--force' => true]);

        $this->assertDatabaseCount('account_balance_snapshots', 1);
    }

    public function test_backoffice_can_view_balance_snapshots_page(): void
    {
        $backoffice = User::factory()->backOffice()->create();

        $this->actingAs($backoffice);

        $this->get('/backoffice/balance-snapshots')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backoffice/BalanceSnapshots')
            );
    }
}
