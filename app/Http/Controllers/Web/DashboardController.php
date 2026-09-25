<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Страница со списками счетов и транзакций
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->isBackOffice()) {
            $this->authorize('viewAny', Account::class);
            $this->authorize('viewAny', Transaction::class);
        } else {
            $this->authorize('viewOwnList', Account::class);
            $this->authorize('viewOwnList', Transaction::class);
        }

        $accountsQuery = Account::query()->with(['customer'])->latest();

        $transactionsQuery = Transaction::query()->with(['sourceAccount', 'targetAccount'])->latest();

        if (!$user->isBackOffice()) {
            $accountsQuery->where('customer_id', $user->customer_id);

            $transactionsQuery->where(function ($query) use ($user): void {
                $query
                    ->whereHas('sourceAccount', fn ($q) => $q->where('customer_id', $user->customer_id))
                    ->orWhereHas('targetAccount', fn ($q) => $q->where('customer_id', $user->customer_id));
            });
        }

        $transactions = $transactionsQuery->paginate(20)->through(fn (Transaction $transaction) => TransactionResource::make($transaction)->resolve());

        $accounts = $accountsQuery->get()->map(fn (Account $account) => AccountResource::make($account)->resolve());

        return inertia('Dashboard/Index', [
            // Банковские счёта
            'accounts' => $accounts,
            // Операции по счетам
            'transactions' => $transactions,
        ]);
    }
}
