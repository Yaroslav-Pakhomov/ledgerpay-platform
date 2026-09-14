<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Auth\DTO\LoginData;
use App\Application\Auth\DTO\RegisterCustomerUserData;
use App\Application\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ApiLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Customer\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class AuthController extends Controller
{
    /**
     * Регистрация Клиента
     *
     *
     * @throws Throwable
     */
    public function register(RegisterRequest $request, AuthService $authService): JsonResponse
    {
        $validated        = $request->validated();
        $registerCustomer = new RegisterCustomerUserData(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password']
        );

        $resultReg = $authService->registerCustomer($registerCustomer);

        return response()->json([
            'token_type'   => 'Bearer',
            'access_token' => $resultReg['token'],
            'user'         => [
                'name'  => $validated['name'],
                'email' => $validated['email'],
            ],
            'customer' => new CustomerResource($resultReg['customer']),
        ], 201);
    }

    public function login(ApiLoginRequest $request, AuthService $authService): JsonResponse
    {
        $validated = $request->validated();

        $loginCustomer = new LoginData(
            email: $validated['email'],
            password: $validated['password'],
            deviceName: $request->deviceName()
        );

        $resultReg = $authService->login($loginCustomer);

        return response()->json([
            'token_type'   => 'Bearer',
            'access_token' => $resultReg['token'],
            'user'         => [
                'name'          => $resultReg['user']->name,
                'email'         => $resultReg['user']->email,
                'is_backoffice' => $resultReg['user']->isBackOffice(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'name'          => $user->name,
                'email'         => $user->email,
                'is_backoffice' => $user->isBackOffice(),
            ],
            'customer' => $user->customer ? new CustomerResource($user->customer) : null,
        ]);
    }

    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authService->logout($request->user());

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }
}
