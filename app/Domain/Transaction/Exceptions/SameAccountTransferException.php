<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Exceptions;

use App\Domain\Shared\Exceptions\IDomainRuleViolation;
use DomainException;

/**
 * Исключение бизнес-логики.
 *
 * Попытка перевода на тот же счет
 */
final class SameAccountTransferException extends DomainException implements IDomainRuleViolation
{
    public function __construct()
    {
        parent::__construct('Попытка перевода на тот же счет.');
    }
}
