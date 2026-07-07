<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\IDomainRuleViolation;
use DomainException;

/**
 * Исключение бизнес-логики.
 *
 * Валюта операции не совпадает с валютой счета
 */
final class CurrencyMismatchException extends DomainException implements IDomainRuleViolation
{
    public function __construct()
    {
        parent::__construct('Валюта операции не совпадает с валютой счета.');
    }
}
