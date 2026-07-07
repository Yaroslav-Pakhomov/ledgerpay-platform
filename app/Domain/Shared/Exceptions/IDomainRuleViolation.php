<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

interface IDomainRuleViolation
{
    public function getMessage(): string;
}
