<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Account\DTO\CreateAccountData;
use App\Application\Account\Services\AccountService;
use App\Application\Audit\Services\AuditLogger;
use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Customer\Exceptions\InactiveCustomerException;
use App\Domain\Customer\Models\Customer;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccountRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * Управление банковскими счетами: создание и просмотр ledger-истории.
 *
 * После создания счёта пишет событие AccountCreated в audit log.
 */
final class AccountController extends Controller
{
    /**
     * @param AuditLogger $audit Сервис записи audit-событий
     */
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Создание банковского счёта.
     *
     * Для backoffice — счёт создаётся для клиента из customer_uuid;
     * для обычного пользователя — для его собственного customer.
     *
     * @param  StoreAccountRequest $request        Валидированные данные (currency, опционально customer_uuid)
     * @param  AccountService      $accountService Сервис открытия счёта с проверкой статуса клиента
     * @return RedirectResponse    Redirect back с flash-сообщением об успехе
     *
     * @throws AuthorizationException    при отсутствии права create
     * @throws ModelNotFoundException    если customer не найден
     * @throws InactiveCustomerException если клиент неактивен
     */
    public function store(StoreAccountRequest $request, AccountService $accountService): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $user = $request->user();

        $validated = $request->validated();

        $customer = $user->isBackOffice() ? Customer::query()->where('uuid', $validated['customer_uuid'])->firstOrFail() : $user->customer;

        $account = $accountService->create(
            new CreateAccountData(
                $customer->uuid,
                $validated['currency'],
            )
        );

        $this->audit->log(
            auditAction: AuditAction::AccountCreated,
            entity: $account,
            metadata: [
                'customer_uuid' => $customer->uuid,
                'currency'      => $account->currency,
            ],
            request: $request,
        );

        return back()->with('success', 'Банковский счет создан.');
    }

    /**
     * История операций (ledger entries) по счёту.
     *
     * @param  string   $uuid UUID банковского счёта
     * @return Response Inertia-страница Dashboard/Ledger с данными счёта и пагинированными записями
     *
     * @throws ModelNotFoundException если счёт не найден
     * @throws AuthorizationException при отсутствии права view
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
