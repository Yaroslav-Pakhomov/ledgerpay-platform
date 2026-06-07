<?php

declare(strict_types=1);

namespace App\Application\Customer\DTO;

/**
 * DTO для создания клиента.
 *
 * Используется Application layer для передачи
 * валидированных данных между transport layer
 * и бизнес-логикой.
 *
 * DTO помогает:
 * - избежать передачи raw Request в сервисы;
 * - сделать use cases независимыми от HTTP;
 * - явно определить входные данные сценария.
 */
final readonly class CreateCustomerData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
