<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Auth\DTO\RegisterCustomerUserData;
use App\Application\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Throwable;

final class AuthController extends Controller
{
    public function loginPage(): Response
    {
        return inertia('Auth/Login');
    }

    public function registerPage(): Response
    {
        return inertia('Auth/Register');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * @throws Throwable
     */
    public function register(RegisterRequest $request, AuthService $authService): RedirectResponse
    {
        $validated = $request->validated();
        $result = $authService->registerCustomer(
            new RegisterCustomerUserData(
                $validated['name'],
                $validated['email'],
                $validated['password']
            )
        );

        Auth::login($result['user']);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
