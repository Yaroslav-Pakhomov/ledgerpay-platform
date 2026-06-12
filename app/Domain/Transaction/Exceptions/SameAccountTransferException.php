<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Exceptions;

use DomainException;

/**
 * Исключение бизнес-логики.
 *
 * Попытка перевода на тот же счет
 */
final class SameAccountTransferException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Попытка перевода на тот же счет.');
    }
}
