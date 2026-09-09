<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Outbox\Jobs\PublishOutboxMessageJob;
use App\Application\Outbox\Services\OutboxPublisher;
use App\Application\Outbox\Services\OutboxWriter;
use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Console\Commands\DispatchPendingOutboxMessagesCommand;
use App\Domain\Account\Models\Account;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\TestCase;
use Throwable;

/**
 * Feature-тесты transactional outbox pattern.
 *
 * Проверяют цепочку {@see OutboxWriter} → `outbox_messages` →
 * {@see DispatchPendingOutboxMessagesCommand} → {@see PublishOutboxMessageJob}
 * → {@see OutboxPublisher} для доменных событий транзакции:
 *
 * - `transaction.created` — при создании pending-транзакции (HTTP);
 * - `transaction.completed` — после успешного {@see ProcessTransactionJob};
 * - `transaction.failed` — после {@see ProcessTransactionJob::failed()};
 * - dispatch pending outbox через artisan-команду;
 * - переход outbox-записи в {@see OutboxStatus::Published}.
 *
 * Queue::fake() изолирует orchestration от фактической публикации в transport.
 * Job->handle() / failed() вызываются вручную — как в {@see TransactionProcessingTest}.
 */
final class OutboxPatternTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    /**
     * Deposit через API создаёт outbox-запись transaction.created со status pending.
     *
     * Outbox пишется в {@see TransactionService} внутри DB-транзакции
     * при первом создании транзакции (не при idempotency-replay).
     */
    public function test_transaction_creation_writes_outbox_message(): void
    {
        Queue::fake();

        $account = Account::factory()
            ->currency('RUB')
            ->withBalance(0)
            ->create();

        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 10_000,
            'currency'            => 'RUB',
        ], [
            'Idempotency-Key' => 'outbox-created-001',
        ])->assertCreated();

        $this->assertDatabaseHas('outbox_messages', [
            'event_name' => 'transaction.created',
            'status'     => OutboxStatus::Pending->value,
        ]);
    }

    /**
     * Успешная обработка ProcessTransactionJob создаёт transaction.completed в outbox.
     *
     * Симулирует worker: job->handle() → {@see TransactionProcessorService::process()}.
     */
    public function test_transaction_completion_writes_outbox_message(): void
    {
        Queue::fake();

        $account = Account::factory(['currency' => 'RUB'])
            ->withBalance(0)
            ->create();

        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 10_000,
            'currency'            => 'RUB',
        ], [
            'Idempotency-Key' => 'outbox-completed-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();

        app(ProcessTransactionJob::class, [
            'transactionId' => $transaction->id,
        ])->handle(app(TransactionProcessorService::class));

        $this->assertDatabaseHas('outbox_messages', [
            'event_name' => 'transaction.completed',
            'status'     => OutboxStatus::Pending->value,
        ]);
    }

    /**
     * Artisan-команда outbox:dispatch-pending ставит {@see PublishOutboxMessageJob} в очередь.
     */
    public function test_dispatch_pending_outbox_command_pushes_jobs(): void
    {
        Queue::fake();

        OutboxMessage::query()->create([
            'event_name'     => 'transaction.created',
            'aggregate_type' => Transaction::class,
            'aggregate_id'   => 1,
            'payload'        => [
                'transaction_uuid' => 'test',
            ],
            'status'       => OutboxStatus::Pending,
            'available_at' => now(),
        ]);

        $this->artisan('outbox:dispatch-pending')
            ->assertSuccessful();

        Queue::assertPushed(PublishOutboxMessageJob::class);
    }

    /**
     * PublishOutboxMessageJob переводит outbox-запись в status published.
     *
     * Transport ({@see OutboxPublisher}) вызывается синхронно в handle() без queue worker.
     */
    public function test_publish_outbox_job_marks_message_as_published(): void
    {
        $message = OutboxMessage::query()->create([
            'event_name'     => 'transaction.created',
            'aggregate_type' => Transaction::class,
            'aggregate_id'   => 1,
            'payload'        => [
                'transaction_uuid' => 'test',
            ],
            'status'       => OutboxStatus::Pending,
            'available_at' => now(),
        ]);

        app(PublishOutboxMessageJob::class, [
            'outboxMessageId' => $message->id,
        ])->handle(app(OutboxPublisher::class));

        $message->refresh();

        $this->assertSame(OutboxStatus::Published, $message->status);
        $this->assertNotNull($message->published_at);
    }

    /**
     * Терминальный сбой обработки создаёт transaction.failed в outbox.
     *
     * Сценарий: withdrawal при недостаточном балансе → handle() бросает исключение →
     * failed() фиксирует Failed и outbox-событие (как после исчерпания retry в production).
     */
    public function test_transaction_failure_writes_outbox_message(): void
    {
        Queue::fake();

        $account = Account::factory(['currency' => 'USD'])
            ->withBalance(1_000)
            ->create();

        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 2_000,
            'currency'            => 'USD',
        ], [
            'Idempotency-Key' => 'outbox-failed-001',
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

        $transaction->refresh();

        $this->assertSame(TransactionStatus::Failed, $transaction->status);
        $this->assertDatabaseCount('outbox_messages', 2);
        $this->assertDatabaseHas('outbox_messages', [
            'event_name' => 'transaction.created',
            'status'     => OutboxStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'event_name' => 'transaction.failed',
            'status'     => OutboxStatus::Pending->value,
        ]);
        $this->assertDatabaseMissing('outbox_messages', [
            'event_name' => 'transaction.completed',
        ]);

        $failedMessage = OutboxMessage::query()
            ->where('event_name', 'transaction.failed')
            ->firstOrFail();

        $this->assertSame($transaction->uuid, $failedMessage->payload['transaction_uuid']);
        $this->assertSame($transaction->failure_reason, $failedMessage->payload['failure_reason']);
    }

    /**
     * Повторный failed() для уже Failed-транзакции не создаёт дубликат outbox-события.
     */
    public function test_failed_job_does_not_duplicate_outbox_when_already_failed(): void
    {
        Queue::fake();

        $account = Account::factory(['currency' => 'USD'])
            ->withBalance(1_000)
            ->create();

        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/withdraw', [
            'source_account_uuid' => $account->uuid,
            'amount'              => 2_000,
            'currency'            => 'USD',
        ], [
            'Idempotency-Key' => 'outbox-failed-idempotent-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();
        $job = app(ProcessTransactionJob::class, ['transactionId' => $transaction->id]);

        try {
            $job->handle(app(TransactionProcessorService::class));
        } catch (Throwable $exception) {
            $job->failed($exception);
        }

        $this->assertDatabaseCount('outbox_messages', 2);

        $job->failed(new RuntimeException('duplicate failed call'));

        $this->assertDatabaseCount('outbox_messages', 2);
        $this->assertSame(1, OutboxMessage::query()->where('event_name', 'transaction.failed')->count());
    }
}
