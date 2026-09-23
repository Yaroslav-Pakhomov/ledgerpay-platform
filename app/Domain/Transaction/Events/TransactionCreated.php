<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Events;

/**
 * Доменное событие: транзакция создана в статусе ожидания (Pending).
 *
 * Эмитится при первом создании агрегата внутри DB-транзакции
 * (не при повторе idempotency-запроса).
 *
 * event_name: `transaction.created`
 */
final readonly class TransactionCreated extends TransactionDomainEvent
{
    public function eventName(): string
    {
        return 'transaction.created';
    }
}
