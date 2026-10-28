<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Account\Models\Account;
use App\Domain\ReadModel\Models\AccountSummaryReadModel;
use App\Domain\ReadModel\Models\TransactionSummaryReadModel;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transaction\Backoffice\TransactionSummaryReadModelResource;
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

        $accountsQuery = AccountSummaryReadModel::query()->latest('id');

        $transactionsQuery = TransactionSummaryReadModel::query()->latest('id');

        if (!$user->isBackOffice()) {
            $customerUuid = $user->customer->uuid;

            $accountsQuery->where('customer_id', $user->customer_id);

            $transactionsQuery->where(function ($query) use ($customerUuid): void {
                $query
                    ->where('source_customer_uuid', $customerUuid)
                    ->orWhere('target_customer_uuid', $customerUuid);
            });
        }

        return inertia('Dashboard/Index', [
            // Банковские счёта
            'accounts' => $accountsQuery->get()->map(fn (AccountSummaryReadModel $account) => [
                'uuid'          => $account->account_uuid,
                'currency'      => $account->currency,
                'balance'       => $account->balance,
                'status'        => $account->status,
                'customer_name' => $account->customer_name,
            ]),
            // Операции по счетам
            'transactions' => $transactionsQuery
                ->paginate(20)
                ->through(fn (TransactionSummaryReadModel $transaction) => TransactionSummaryReadModelResource::make($transaction)->resolve()),
        ]);
    }
}
