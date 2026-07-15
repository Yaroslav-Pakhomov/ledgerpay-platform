<?php

namespace App\Providers;

// use Illuminate\Support\ServiceProvider;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use App\Domain\Transaction\Models\Transaction;
use App\Policies\AccountPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Account::class     => AccountPolicy::class,
        Transaction::class => TransactionPolicy::class,
        Customer::class    => CustomerPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
