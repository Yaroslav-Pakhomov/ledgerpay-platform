<?php

declare(strict_types=1);

namespace App\Domain\Customer\Exceptions;

use App\Domain\Shared\Exceptions\IDomainRuleViolation;
use DomainException;

/**
 * Исключение бизнес-логики.
 *
 * Клиент неактивен
 */
final class InactiveCustomerException extends DomainException implements IDomainRuleViolation
{
    public function __construct()
    {
        parent::__construct('Не удается открыть счет для неактивного клиента.');
    }
}
