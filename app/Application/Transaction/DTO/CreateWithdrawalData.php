<?php

declare(strict_types=1);

namespace App\Application\Transaction\DTO;

/**
 * DTO уровня Application.
 *
 * Инкапсулирует данные, необходимые для создания
 * операции списания денежных средств.
 *
 * Используется для передачи данных от HTTP-слоя
 * в TransactionService без зависимости от Request.
 */
final readonly class CreateWithdrawalData
{
    public function __construct(
        public string $sourceAccountUuid,
        public int $amount,
        public string $currency,
        public string $idempotencyKey,
    ) {}
}
