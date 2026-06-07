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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

final class AccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $accounts = Account::query()
            ->with('customer')
            ->latest()
            ->paginate(20);

        return AccountResource::collection($accounts);
    }

    public function store(
        StoreAccountRequest $request,
        AccountService      $service,
    ): JsonResponse
    {
        $customer = Customer::query()
            ->where('uuid', $request->string('customer_uuid')->toString())
            ->firstOrFail();

        $account = $service->create(
            new CreateAccountData(
                customerId: $customer->id,
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

        return new AccountResource($account);
    }
}
