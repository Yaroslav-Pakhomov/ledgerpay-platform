<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use DomainException;

/**
 * Исключение бизнес-логики.
 *
 * Счет неактивен
 */
final class InactiveAccountException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Счет неактивен.');
    }
}
