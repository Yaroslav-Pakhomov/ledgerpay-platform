<?php

declare(strict_types=1);

namespace App\Application\Auth\DTO;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}
}
