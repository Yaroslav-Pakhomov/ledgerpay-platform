<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Exceptions\SameAccountTransferException;

/**
 * Доменное правило для переводов между счетами.
 */
final class TransferPolicy
{
    /**
     * Доменное правило: перевод возможен только между разными счетами.
     */
    public function assertDifferentAccounts(Account $source, Account $target): void
    {
        if ($source->id === $target->id) {
            throw new SameAccountTransferException;
        }
    }
}
