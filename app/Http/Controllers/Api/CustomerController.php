<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Customer\DTO\CreateCustomerData;
use App\Application\Customer\Services\CustomerService;
use App\Domain\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Resources\Customer\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

// use Symfony\Component\HttpFoundation\Response as ResponseAlias;

final class CustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->latest()
            ->paginate(20);

        return CustomerResource::collection($customers);
    }

    public function store(
        StoreCustomerRequest $request,
        CustomerService $service,
    ): JsonResponse {
        $this->authorize('create', Customer::class);

        $customer = $service->create(
            new CreateCustomerData(
                name: $request->string('name')->toString(),
                email: $request->string('email')->toString(),
            )
        );

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(ResponseAlias::HTTP_CREATED);
    }

    public function show(string $uuid): CustomerResource
    {
        $customer = Customer::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }
}
