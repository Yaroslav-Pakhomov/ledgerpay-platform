<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
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

        return inertia('Dashboard/Index', [
            // Банковские счёта
            'accounts' => $accountsQuery->get()->map(fn (Account $account) => [
                'id'            => $account->id,
                'uuid'          => $account->uuid,
                'currency'      => $account->currency,
                'balance'       => $account->balance,
                'status'        => $account->status->value,
                'customer_name' => $account->customer->name,
            ]),
            // Операции по счетам
            'transactions' => $transactionsQuery->limit(20)->get()->map(fn (Transaction $transaction) => [
                'id'       => $transaction->id,
                'uuid'     => $transaction->uuid,
                'currency' => $transaction->currency,
                'amount'   => $transaction->amount,

                'type'   => $transaction->type->value,
                'status' => $transaction->status->value,

                'source_account_uuid' => $transaction->sourceAccount?->uuid,
                'target_account_uuid' => $transaction->targetAccount?->uuid,

                'failure_reason' => $transaction->failure_reason,

                'created_at' => $transaction->created_at->toDateTimeString(),
            ]),
        ]);
    }
}
