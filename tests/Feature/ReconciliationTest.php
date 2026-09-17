<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Reconciliation\Services\ReconciliationService;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_matches_account_balance_against_ledger(): void
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
            'idempotency_key'   => 'reconciliation-match-001',
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

        $report = app(ReconciliationService::class)->checkAccount($account);

        $this->assertSame(ReconciliationStatus::Matched, $report->status);
        $this->assertSame(10_000, $report->account_balance);
        $this->assertSame(10_000, $report->ledger_balance);
        $this->assertSame(0, $report->difference);
    }

    public function test_reconciliation_detects_mismatch(): void
    {
        $account = Account::factory()
            ->withBalance(15_000)
            ->create();

        $transaction = Transaction::query()->create([
            'type'              => TransactionType::Deposit,
            'status'            => TransactionStatus::Completed,
            'source_account_id' => null,
            'target_account_id' => $account->id,
            'amount'            => 10_000,
            'currency'          => 'RUB',
            'idempotency_key'   => 'reconciliation-mismatch-001',
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

        $report = app(ReconciliationService::class)->checkAccount($account);

        $this->assertSame(ReconciliationStatus::Mismatched, $report->status);
        $this->assertSame(15_000, $report->account_balance);
        $this->assertSame(10_000, $report->ledger_balance);
        $this->assertSame(5_000, $report->difference);
    }

    public function test_reconciliation_command_creates_reports(): void
    {
        Account::factory()
            ->withBalance(0)
            ->count(2)
            ->create();

        $this->artisan('reconciliation:run')
            ->assertSuccessful();

        $this->assertDatabaseCount('reconciliation_reports', 2);
    }

    public function test_backoffice_can_view_reconciliation_page(): void
    {
        $backoffice = User::factory()->backOffice()->create();

        $this->actingAs($backoffice);

        $this->get('/backoffice/reconciliation')
            ->assertOk();
    }

    public function test_customer_cannot_view_reconciliation_page(): void
    {
        $customer = Customer::factory()->create();
        $user     = User::factory()->forCustomer($customer)->create();

        $this->actingAs($user);

        $this->get('/backoffice/reconciliation')
            ->assertForbidden();
    }

    public function test_backoffice_can_run_reconciliation(): void
    {
        $backoffice = User::factory()->backOffice()->create();
        Account::factory()->withBalance(0)->create();

        $this->actingAs($backoffice);

        $this->post(route('backoffice.reconciliation.run'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('reconciliation_reports', 1);
    }

    public function test_backoffice_can_run_reconciliation_for_single_account(): void
    {
        $backoffice = User::factory()->backOffice()->create();
        $account    = Account::factory()->withBalance(0)->create();

        $this->actingAs($backoffice);

        $this->post(route('backoffice.reconciliation.accounts.run', $account->uuid))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('reconciliation_reports', 1);
    }

    public function test_customer_cannot_run_reconciliation(): void
    {
        $customer = Customer::factory()->create();
        $user     = User::factory()->forCustomer($customer)->create();

        $this->actingAs($user);

        $this->post(route('backoffice.reconciliation.run'))
            ->assertForbidden();
    }

    public function test_backoffice_reconciliation_page_shows_reports(): void
    {
        $backoffice = User::factory()->backOffice()->create();
        $account    = Account::factory()->withBalance(0)->create();

        app(ReconciliationService::class)->checkAccount($account);

        $this->actingAs($backoffice);

        $this->get('/backoffice/reconciliation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backoffice/Reconciliation')
                ->has('reports.data', 1)
                ->where('reports.data.0.account_balance', 0)
                ->where('reports.data.0.status', 'matched')
            );
    }

    public function test_backoffice_reconciliation_filters_by_status(): void
    {
        $backoffice = User::factory()->backOffice()->create();

        Account::factory()->withBalance(0)->create(); // matched
        Account::factory()->withBalance(5_000)->create(); // mismatched (no ledger)

        app(ReconciliationService::class)->checkAllAccounts();

        $this->actingAs($backoffice);

        $this->get('/backoffice/reconciliation?status=mismatched')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('reports.data', 1)
                ->where('reports.data.0.status', 'mismatched')
                ->where('filters.status', 'mismatched')
            );
    }

    public function test_reconciliation_command_fails_on_mismatch(): void
    {
        Account::factory()->withBalance(5_000)->create();

        $this->artisan('reconciliation:run')
            ->assertFailed();
    }

    public function test_customer_cannot_run_reconciliation_for_single_account(): void
    {
        $customer = Customer::factory()->create();
        $user     = User::factory()->forCustomer($customer)->create();
        $account  = Account::factory()->for($customer)->withBalance(0)->create();

        $this->actingAs($user);

        $this->post(route('backoffice.reconciliation.accounts.run', $account->uuid))
            ->assertForbidden();
    }
}
