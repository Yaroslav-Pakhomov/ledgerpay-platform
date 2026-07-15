<?php

declare(strict_types=1);

namespace App\Application\Auth\DTO;

final readonly class RegisterCustomerUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
