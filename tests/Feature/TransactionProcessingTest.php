<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Throwable;

/**
 * Feature-тесты async-контракта money-flow.
 *
 * Проверяют application layer отдельно от sync end-to-end сценариев
 * в TransactionApiTest: TransactionService создает pending-транзакцию
 * и dispatch'ит ProcessTransactionJob, а TransactionProcessorService
 * выполняет доменную обработку при ручном вызове job->handle().
 *
 * Queue::fake() изолирует orchestration (создание + dispatch)
 * от фактического движения денег (баланс, ledger, статус Completed/Failed).
 */
final class TransactionProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_creates_pending_transaction_and_dispatches_job(): void
    {
        Queue::fake();

        $account = Account::factory()
            ->currency('USD')
            ->withBalance(0)
            ->create();

        $response = $this->postJson(
            uri: '/api/transactions/deposit',
            data: [
                'target_account_uuid' => $account->uuid,
                'amount'              => 10_000,
                'currency'            => 'USD',
            ],
            headers: [
                'Idempotency-Key' => 'deposit-test-001',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.type', TransactionType::Deposit->value)
            ->assertJsonPath('data.status', TransactionStatus::Pending->value)
            ->assertJsonPath('data.amount', 10_000);

        $this->assertDatabaseHas('transactions', [
            'type'              => TransactionType::Deposit->value,
            'status'            => TransactionStatus::Pending->value,
            'target_account_id' => $account->id,
            'amount'            => 10_000,
            'currency'          => 'USD',
            'idempotency_key'   => 'deposit-test-001',
        ]);

        Queue::assertPushed(ProcessTransactionJob::class);
    }

    public function test_idempotency_key_returns_existing_transaction_without_dispatching_duplicate_job(): void
    {
        Queue::fake();

        $account = Account::factory()
            ->currency('USD')
            ->withBalance(0)
            ->create();

        $payload = [
            'target_account_uuid' => $account->uuid,
            'amount'              => 10_000,
            'currency'            => 'USD',
        ];

        $first = $this->postJson('/api/transactions/deposit', $payload, [
            'Idempotency-Key' => 'deposit-idempotency-001',
        ]);

        $second = $this->postJson('/api/transactions/deposit', $payload, [
            'Idempotency-Key' => 'deposit-idempotency-001',
        ]);

        $first->assertCreated();
        $second->assertOk();

        $this->assertSame(
            $first->json('data.uuid'),
            $second->json('data.uuid'),
        );

        $this->assertSame(1, Transaction::query()->count());

        Queue::assertPushed(ProcessTransactionJob::class, 1);
    }

    public function test_deposit_job_increases_balance_and_creates_credit_ledger_entry(): void
    {
        Queue::fake();

        $account = Account::factory()
            ->currency('USD')
            ->withBalance(0)
            ->create();

        $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 50_000,
            'currency'            => 'USD',
        ], [
            'Idempotency-Key' => 'deposit-processing-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();

        Queue::assertPushed(ProcessTransactionJob::class);

        app(ProcessTransactionJob::class, [
            'transactionId' => $transaction->id,
        ])->handle(app(TransactionProcessorService::class));

        $account->refresh();
        $transaction->refresh();

        $this->assertSame(50_000, $account->balance);
        $this->assertSame(TransactionStatus::Completed, $transaction->status);

        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => 'credit',
            'amount'         => 50_000,
            'currency'       => 'USD',
            'balance_after'  => 50_000,
        ]);
    }

    public function test_withdrawal_decreases_balance_and_creates_debit_ledger_entry(): void
    {
        Queue::fake();

        $account = Account::factory()
            ->currency('USD')
            ->withBalance(100_000)
            ->create();

        $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 30_000,
            'currency'            => 'USD',
        ], [
            'Idempotency-Key' => 'withdraw-processing-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();

        app(ProcessTransactionJob::class, [
            'transactionId' => $transaction->id,
        ])->handle(app(TransactionProcessorService::class));

        $account->refresh();
        $transaction->refresh();

        $this->assertSame(70_000, $account->balance);
        $this->assertSame(TransactionStatus::Completed, $transaction->status);

        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'direction'      => 'debit',
            'amount'         => 30_000,
            'balance_after'  => 70_000,
        ]);
    }

    public function test_transfer_moves_money_between_accounts_and_creates_double_entry_ledger(): void
    {
        Queue::fake();

        $source = Account::factory()
            ->currency('USD')
            ->withBalance(100_000)
            ->create();

        $target = Account::factory()
            ->currency('USD')
            ->withBalance(5_000)
            ->create();

        $this->postJson('/api/transactions/transfer', [
            'source_account_uuid' => $source->uuid,
            'target_account_uuid' => $target->uuid,
            'amount'              => 25_000,
            'currency'            => 'USD',
        ], [
            'Idempotency-Key' => 'transfer-processing-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();

        app(ProcessTransactionJob::class, [
            'transactionId' => $transaction->id,
        ])->handle(app(TransactionProcessorService::class));

        $source->refresh();
        $target->refresh();
        $transaction->refresh();

        $this->assertSame(75_000, $source->balance);
        $this->assertSame(30_000, $target->balance);
        $this->assertSame(TransactionStatus::Completed, $transaction->status);

        $this->assertSame(2, LedgerEntry::query()->count());

        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $transaction->id,
            'account_id'     => $source->id,
            'direction'      => 'debit',
            'amount'         => 25_000,
            'balance_after'  => 75_000,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'transaction_id' => $transaction->id,
            'account_id'     => $target->id,
            'direction'      => 'credit',
            'amount'         => 25_000,
            'balance_after'  => 30_000,
        ]);
    }

    public function test_withdrawal_fails_when_balance_is_insufficient(): void
    {
        Queue::fake();

        $account = Account::factory()
            ->currency('USD')
            ->withBalance(1_000)
            ->create();

        $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 2_000,
            'currency'            => 'USD',
        ], [
            'Idempotency-Key' => 'withdraw-insufficient-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();

        try {
            app(ProcessTransactionJob::class, [
                'transactionId' => $transaction->id,
            ])->handle(app(TransactionProcessorService::class));
        } catch (Throwable $exception) {
            app(ProcessTransactionJob::class, [
                'transactionId' => $transaction->id,
            ])->failed($exception);
        }

        $account->refresh();
        $transaction->refresh();

        $this->assertSame(1_000, $account->balance);
        $this->assertSame(TransactionStatus::Failed, $transaction->status);
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertNotNull($transaction->failure_reason);
    }

    public function test_idempotency_key_header_is_required(): void
    {
        Queue::fake();

        $account = Account::factory()->create();

        $response = $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 10_000,
            'currency'            => 'USD',
        ]);

        $response->assertUnprocessable();

        $this->assertSame(0, Transaction::query()->count());

        Queue::assertNothingPushed();
    }
}
