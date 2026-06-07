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
use Illuminate\Http\Response;

// use Symfony\Component\HttpFoundation\Response as ResponseAlias;

final class CustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->latest()
            ->paginate(20);

        return CustomerResource::collection($customers);
    }

    public function store(
        StoreCustomerRequest $request,
        CustomerService $service,
    ): JsonResponse {
        $customer = $service->create(
            new CreateCustomerData(
                name: $request->string('name')->toString(),
                email: $request->string('email')->toString(),
            )
        );

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $uuid): CustomerResource
    {
        $customer = Customer::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return new CustomerResource($customer);
    }
}
