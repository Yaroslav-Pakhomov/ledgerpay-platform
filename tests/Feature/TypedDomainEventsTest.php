<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Outbox\Mappers\DomainEventToOutboxMessageMapper;
use App\Application\Outbox\Services\OutboxWriter;
use App\Domain\Account\Exceptions\InsufficientFundsException;
use App\Domain\Account\Models\Account;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Shared\Events\IDomainEvent;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Events\TransactionCreated;
use App\Domain\Transaction\Events\TransactionFailed;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-тесты типизированных доменных событий и преобразователя outbox.
 *
 * Проверяют контракт {@see IDomainEvent} → {@see DomainEventToOutboxMessageMapper}
 * → {@see OutboxWriter::recordEvent()} для {@see TransactionCreated}:
 *
 * - структура тела события (payload);
 * - преобразование в структуру строки outbox;
 * - сохранение в `outbox_messages`.
 */
final class TypedDomainEventsTest extends TestCase
{
    use RefreshDatabase;

    /** Тело события {@see TransactionCreated} содержит ожидаемые поля агрегата. */
    public function test_transaction_created_event_has_expected_payload(): void
    {
        $account = Account::factory()->create();

        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 10_000,
            'currency'               => 'RUB',
            'idempotency_key'        => 'typed-event-001',
            'idempotency_expires_at' => now()->addDay(),
        ]);

        $event = new TransactionCreated($transaction);

        $this->assertSame('transaction.created', $event->eventName());
        $this->assertSame(Transaction::class, $event->aggregateType());
        $this->assertSame($transaction->id, $event->aggregateId());
        $this->assertSame($transaction->uuid, $event->aggregateUuid());

        $this->assertSame([
            'transaction_uuid'  => $transaction->uuid,
            'type'              => 'deposit',
            'status'            => 'pending',
            'amount'            => 10_000,
            'currency'          => 'RUB',
            'source_account_id' => null,
            'target_account_id' => $account->id,
            'processed_at'      => null,
        ], $event->payload());
    }

    /** {@see DomainEventToOutboxMessageMapper} формирует структуру строки outbox. */
    public function test_mapper_converts_domain_event_to_outbox_shape(): void
    {
        $account = Account::factory()->create();

        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 10_000,
            'currency'               => 'RUB',
            'idempotency_key'        => 'typed-event-002',
            'idempotency_expires_at' => now()->addDay(),
        ]);

        $mapped = app(DomainEventToOutboxMessageMapper::class)
            ->map(new TransactionCreated($transaction));

        $this->assertSame('transaction.created', $mapped['event_name']);
        $this->assertSame(Transaction::class, $mapped['aggregate_type']);
        $this->assertSame($transaction->id, $mapped['aggregate_id']);
        $this->assertSame($transaction->uuid, $mapped['aggregate_uuid']);
        $this->assertSame('deposit', $mapped['payload']['type']);
    }

    /** {@see OutboxWriter::recordEvent()} сохраняет типизированное событие в БД. */
    public function test_outbox_writer_records_typed_event(): void
    {
        $account = Account::factory()->create();

        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 10_000,
            'currency'               => 'RUB',
            'idempotency_key'        => 'typed-event-003',
            'idempotency_expires_at' => now()->addDay(),
        ]);

        app(OutboxWriter::class)->recordEvent(
            new TransactionCreated($transaction)
        );

        $message = OutboxMessage::query()->firstOrFail();

        $this->assertSame('transaction.created', $message->event_name);
        $this->assertSame($transaction->uuid, $message->aggregate_uuid);
        $this->assertSame('deposit', $message->payload['type']);
    }

    /** {@see TransactionFailed} добавляет failure_reason и exception_class в тело события. */
    public function test_transaction_failed_event_includes_failure_fields(): void
    {
        $account = Account::factory()->create();

        $transaction = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Failed,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 500,
            'currency'               => 'RUB',
            'idempotency_key'        => 'typed-event-failed-001',
            'idempotency_expires_at' => now()->addDay(),
            'failure_reason'         => 'Insufficient funds',
        ]);

        $event = new TransactionFailed(
            transaction: $transaction,
            reason: 'Insufficient funds',
            exceptionClass: InsufficientFundsException::class,
        );

        $this->assertSame('transaction.failed', $event->eventName());
        $this->assertSame('Insufficient funds', $event->payload()['failure_reason']);
        $this->assertSame(InsufficientFundsException::class, $event->payload()['exception_class']);
    }
}
