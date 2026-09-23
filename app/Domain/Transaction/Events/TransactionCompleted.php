<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Events;

use App\Domain\Transaction\Enums\TransactionStatus;

/**
 * Доменное событие: транзакция успешно обработана.
 *
 * Эмитится внутри DB-транзакции после перевода агрегата
 * в {@see TransactionStatus::Completed}.
 *
 * event_name: `transaction.completed`
 */
final readonly class TransactionCompleted extends TransactionDomainEvent
{
    public function eventName(): string
    {
        return 'transaction.completed';
    }
}
