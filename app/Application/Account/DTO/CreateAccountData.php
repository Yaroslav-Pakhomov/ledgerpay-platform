<?php

declare(strict_types=1);

namespace App\Application\Account\DTO;

/**
 * DTO для создания банковского счета.
 *
 * Используется для передачи валидированных данных
 * из transport layer в application service.
 *
 * DTO изолирует бизнес-логику
 * от HTTP Request и framework-specific объектов.
 */
final readonly class CreateAccountData
{
    public function __construct(
        public string $customerUuid,
        public string $currency,
    ) {}
}
