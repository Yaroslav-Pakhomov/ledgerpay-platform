<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Account\DTO\CreateAccountData;
use App\Application\Account\Services\AccountService;
use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\LedgerEntry\LedgerEntryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

final class AccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::query()
            ->with('customer')
            ->latest()
            ->paginate(20);

        return AccountResource::collection($accounts);
    }

    public function store(
        StoreAccountRequest $request,
        AccountService $service,
    ): JsonResponse {
        $this->authorize('create', Account::class);

        $user = $request->user();

        if ($user->isBackOffice()) {
            $customer = Customer::query()->where('uuid', $request->string('customer_uuid')->toString())->firstOrFail();
        } else {
            $customer = $user->customer;
        }

        if (!$customer instanceof Customer) {
            abort(ResponseAlias::HTTP_UNPROCESSABLE_ENTITY, 'Customer not found.');
        }

        $account = $service->create(
            new CreateAccountData(
                customerUuid: $customer->uuid,
                currency: $request->string('currency')->toString(),
            )
        );

        return (new AccountResource($account->load('customer')))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    public function show(string $uuid): AccountResource
    {
        $account = Account::query()
            ->with('customer')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $account);

        return new AccountResource($account);
    }

    public function balance(string $uuid): JsonResponse
    {
        $account = Account::query()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->authorize('view', $account);

        return response()->json([
            'account_uuid' => $account->uuid,
            'balance'      => $account->balance,
            'currency'     => $account->currency,
        ]);
    }

    public function ledger(string $uuid): AnonymousResourceCollection
    {
        $account = Account::query()
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->authorize('view', $account);

        $entries = $account->ledgerEntries()
            ->with(['transaction', 'account'])
            ->latest()
            ->paginate(50);

        return LedgerEntryResource::collection($entries);
    }
}
