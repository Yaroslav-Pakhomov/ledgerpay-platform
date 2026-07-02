<?php

declare(strict_types=1);

namespace App\Application\Transaction\Results;

use App\Domain\Transaction\Models\Transaction;

/**
 * Результат application-сценария создания транзакции.
 *
 * В DDD application layer возвращает не только доменную сущность,
 * но и контекст выполнения use case. Флаг {@see $created} отличает
 * первичное создание агрегата Transaction от повторного idempotency-запроса.
 *
 * Это позволяет orchestration-слою (TransactionService) dispatch'ить
 * ProcessTransactionJob только один раз — при фактическом появлении
 * новой pending-транзакции, не затрагивая доменную модель.
 */
final readonly class TransactionCreationResult
{
    /**
     * @param Transaction $transaction Агрегат транзакции — новый или найденный по idempotency_key.
     * @param bool        $created     true, если агрегат создан в текущем вызове use case.
     */
    public function __construct(
        public Transaction $transaction,
        public bool $created,
    ) {}
}
