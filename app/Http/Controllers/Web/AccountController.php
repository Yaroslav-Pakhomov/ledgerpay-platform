<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Account\DTO\CreateAccountData;
use App\Application\Account\Services\AccountService;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccountRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

final class AccountController extends Controller
{
    /**
     * Создание банковского счёта
     */
    public function store(StoreAccountRequest $request, AccountService $accountService): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $user = $request->user();

        $validated = $request->validated();

        $customer = $user->isBackOffice() ? Customer::query()->where('uuid', $validated['customer_uuid'])->firstOrFail() : $user->customer;

        $accountService->create(
            new CreateAccountData(
                $customer->uuid,
                $validated['currency'],
            )
        );

        return back()->with('success', 'Банковский счет создан.');
    }

    /**
     * Получение истории перевод по счёту
     */
    public function ledger(string $uuid): Response
    {
        $account = Account::query()->where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $account);

        return inertia('Dashboard/Ledger', [
            'account' => [
                'uuid'     => $account->uuid,
                'currency' => $account->currency,
                'balance'  => $account->balance,
                'status'   => $account->status->value,
            ],
            'entries' => $account->ledgerEntries()
                ->with('transaction')
                ->latest()
                ->paginate(50)
                ->through(fn (LedgerEntry $entry) => [
                    'id'               => $entry->id,
                    'direction'        => $entry->direction->value,
                    'amount'           => $entry->amount,
                    'currency'         => $entry->currency,
                    'balance_after'    => $entry->balance_after,
                    'transaction_uuid' => $entry->transaction->uuid,
                    'created_at'       => $entry->created_at->toDateTimeString(),
                ]),
        ]);
    }
}
