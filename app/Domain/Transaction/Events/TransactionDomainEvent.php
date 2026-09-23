<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Events;

use App\Domain\Shared\Events\IDomainEvent;
use App\Domain\Transaction\Models\Transaction;

/**
 * Базовое типизированное доменное событие для агрегата {@see Transaction}.
 *
 * Общая структура тела события (payload) для всех событий транзакции:
 * - transaction_uuid, type, status, amount, currency;
 * - source_account_id, target_account_id, processed_at.
 *
 * Конкретное имя события задаёт наследник через {@see eventName()}.
 *
 * @see TransactionCreated
 * @see TransactionCompleted
 * @see TransactionFailed
 * @see TransactionRetried
 */
abstract readonly class TransactionDomainEvent implements IDomainEvent
{
    /** @param array<string, mixed> $extraHeaders */
    public function __construct(
        protected Transaction $transaction,
        protected array $extraHeaders = [],
    ) {}

    abstract public function eventName(): string;

    public function aggregateType(): string
    {
        return $this->transaction::class;
    }

    public function aggregateId(): int
    {
        return (int) $this->transaction->getKey();
    }

    public function aggregateUuid(): ?string
    {
        return $this->transaction->uuid;
    }

    /**
     * @return array{
     *     transaction_uuid: string,
     *     type: string,
     *     status: string,
     *     amount: int,
     *     currency: string,
     *     source_account_id: int|null,
     *     target_account_id: int|null,
     *     processed_at: string|null
     * }
     */
    public function payload(): array
    {
        return [
            'transaction_uuid'  => $this->transaction->uuid,
            'type'              => $this->transaction->type->value,
            'status'            => $this->transaction->status->value,
            'amount'            => $this->transaction->amount,
            'currency'          => $this->transaction->currency,
            'source_account_id' => $this->transaction->source_account_id,
            'target_account_id' => $this->transaction->target_account_id,
            'processed_at'      => $this->transaction->processed_at?->toISOString(),
        ];
    }

    public function headers(): array
    {
        return $this->extraHeaders;
    }
}
