<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use DomainException;

/**
 * Исключение бизнес-логики.
 *
 * Недостаточно средств
 */
final class InsufficientFundsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Недостаточно средств.');
    }
}
