<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Events;

/**
 * Доменное событие: транзакция со сбоем повторно поставлена в обработку.
 *
 * Эмитится при ручном retry (Failed → Pending).
 *
 * event_name: `transaction.retried`
 */
final readonly class TransactionRetried extends TransactionDomainEvent
{
    public function eventName(): string
    {
        return 'transaction.retried';
    }
}
