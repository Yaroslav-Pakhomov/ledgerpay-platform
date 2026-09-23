<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Events;

use App\Domain\Transaction\Models\Transaction;
use Override;

/**
 * Доменное событие: терминальный сбой обработки транзакции.
 *
 * Эмитится внутри DB-транзакции при фиксации статуса сбоя (Failed)
 * после исчерпания повторов worker.
 *
 * event_name: `transaction.failed`
 */
final readonly class TransactionFailed extends TransactionDomainEvent
{
    /**
     * @param Transaction          $transaction    Агрегат с заполненным failure_reason
     * @param string               $reason         Текст ошибки (обычно message исключения)
     * @param string|null          $exceptionClass FQCN исключения для диагностики внешних потребителей
     * @param array<string, mixed> $extraHeaders   Дополнительные метаданные (headers)
     */
    public function __construct(
        Transaction $transaction,
        private string $reason,
        private ?string $exceptionClass = null,
        array $extraHeaders = [],
    ) {
        parent::__construct($transaction, $extraHeaders);
    }

    public function eventName(): string
    {
        return 'transaction.failed';
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
     *     processed_at: string|null,
     *     failure_reason: string,
     *     exception_class?: string
     * }
     */
    #[Override]
    public function payload(): array
    {
        $payload = array_merge(parent::payload(), [
            'failure_reason' => $this->reason,
        ]);

        if ($this->exceptionClass !== null) {
            $payload['exception_class'] = $this->exceptionClass;
        }

        return $payload;
    }
}
