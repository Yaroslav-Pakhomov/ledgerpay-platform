<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Transaction\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isBackOffice();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        if ($user->isBackOffice()) {
            return true;
        }

        $customerId = $user->customer_id;

        return $transaction->sourceAccount?->customer_id === $customerId || $transaction->targetAccount?->customer_id === $customerId;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isBackOffice() || $user->customer_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function retry(User $user, Transaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }
}
