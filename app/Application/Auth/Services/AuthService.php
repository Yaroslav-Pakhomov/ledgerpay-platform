<?php

declare(strict_types=1);

namespace App\Application\Auth\Services;

use App\Application\Auth\DTO\LoginData;
use App\Application\Auth\DTO\RegisterCustomerUserData;
use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AuthService
{
    /**
     * Создает Customer + User атомарно.
     *
     * Регистрация пользователя и создание customer-профиля
     * не должны расходиться по состоянию.
     *
     *
     * @throws Throwable
     */
    public function registerCustomer(RegisterCustomerUserData $customerData): array
    {

        return DB::transaction(function () use ($customerData) {
            $customer = Customer::query()->create([
                'name'   => $customerData->name,
                'email'  => $customerData->email,
                'status' => CustomerStatus::Active,
            ]);

            $user = User::query()->create([
                'name'        => $customerData->name,
                'email'       => $customerData->email,
                'password'    => $customerData->password,
                'customer_id' => $customer->id,
            ]);

            $token = $user->createToken(
                name: 'default',
                abilities: ['customer'],
            )->plainTextToken;

            return [
                'user'     => $user,
                'customer' => $customer,
                'token'    => $token,
            ];
        });
    }

    public function login(LoginData $loginData): array
    {
        $user = User::query()->where('email', $loginData->email)->first();

        if (!$user instanceof User || !Hash::check($loginData->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $abilities = $user->isBackOffice() ? ['backoffice'] : ['customer'];

        $token = $user->createToken(
            name: $loginData->deviceName,
            abilities: $abilities,
        )->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
