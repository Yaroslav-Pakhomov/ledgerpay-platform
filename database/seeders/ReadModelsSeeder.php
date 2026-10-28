<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\ReadModel\Projectors\AccountSummaryProjector;
use App\Application\ReadModel\Projectors\TransactionSummaryProjector;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Seeder;

final class ReadModelsSeeder extends Seeder
{
    public function run(): void
    {
        $accountProjector     = app(AccountSummaryProjector::class);
        $transactionProjector = app(TransactionSummaryProjector::class);

        Account::query()->with('customer')->orderBy('id')->each(fn (Account $account) => $accountProjector->project($account));

        Transaction::query()->with(['sourceAccount.customer', 'targetAccount.customer'])->orderBy('id')->each(fn (Transaction $transaction) => $transactionProjector->project($transaction));
    }
}
