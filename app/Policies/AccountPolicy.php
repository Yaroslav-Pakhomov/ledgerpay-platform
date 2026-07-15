<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Account\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBackOffice();
    }

    public function view(User $user, Account $account): bool
    {
        if ($user->isBackOffice()) {
            return true;
        }

        return $user->customer_id === $account->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->isBackOffice() || $user->customer_id !== null;
    }
}
