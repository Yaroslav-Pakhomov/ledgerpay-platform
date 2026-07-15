<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Customer\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBackOffice();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isBackOffice() || $user->customer_id === $customer->id;
    }

    public function create(User $user): bool
    {
        return $user->isBackOffice();
    }
}
