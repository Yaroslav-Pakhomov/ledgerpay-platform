<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\ReadModel\Projectors\AccountSummaryProjector;
use App\Application\ReadModel\Projectors\TransactionSummaryProjector;
use App\Domain\Account\Models\Account;
use App\Domain\ReadModel\Models\AccountSummaryReadModel;
use App\Domain\ReadModel\Models\TransactionSummaryReadModel;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReadModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_summary_projection_is_created(): void
    {
        $account = Account::factory()
            ->withBalance(10_000)
            ->create();

        app(AccountSummaryProjector::class)->project($account);

        $this->assertDatabaseHas('account_summary_read_models', [
            'account_id'   => $account->id,
            'account_uuid' => $account->uuid,
            'balance'      => 10_000,
            'currency'     => $account->currency,
        ]);
    }

    public function test_transaction_summary_projection_is_created(): void
    {
        $account = Account::factory()->create();

        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 1000,
            'currency'               => 'RUB',
            'idempotency_key'        => 'projection-test-001',
            'idempotency_expires_at' => now()->addDay(),
        ]);

        app(TransactionSummaryProjector::class)->project($transaction);

        $this->assertDatabaseHas('transaction_summary_read_models', [
            'transaction_id'   => $transaction->id,
            'transaction_uuid' => $transaction->uuid,
            'type'             => 'deposit',
            'status'           => 'pending',
            'amount'           => 1000,
        ]);
    }

    public function test_read_models_rebuild_command(): void
    {
        $account = Account::factory()->create();

        Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 1000,
            'currency'               => 'RUB',
            'idempotency_key'        => 'projection-rebuild-001',
            'idempotency_expires_at' => now()->addDay(),
        ]);

        $this->runArtisanSuccessfully('read-models:rebuild', ['--truncate' => true]);

        $this->assertSame(1, AccountSummaryReadModel::query()->count());
        $this->assertSame(1, TransactionSummaryReadModel::query()->count());
    }
}
