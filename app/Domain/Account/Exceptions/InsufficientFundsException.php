<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\IDomainRuleViolation;
use DomainException;

/**
 * Исключение бизнес-логики.
 *
 * Недостаточно средств
 */
final class InsufficientFundsException extends DomainException implements IDomainRuleViolation
{
    public function __construct()
    {
        parent::__construct('Недостаточно средств.');
    }
}
