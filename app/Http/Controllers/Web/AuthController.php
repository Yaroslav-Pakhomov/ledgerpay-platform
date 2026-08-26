<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Audit\Services\AuditLogger;
use App\Application\Auth\DTO\RegisterCustomerUserData;
use App\Application\Auth\Services\AuthService;
use App\Domain\Audit\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Throwable;

/**
 * Веб-аутентификация: страницы входа/регистрации и session-based login/logout.
 *
 * После успешного входа, регистрации и выхода пишет события в audit log.
 */
final class AuthController extends Controller
{
    /**
     * @param AuditLogger $audit Сервис записи audit-событий
     */
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Страница входа.
     *
     * @return Response Inertia-страница Auth/Login
     */
    public function loginPage(): Response
    {
        return inertia('Auth/Login');
    }

    /**
     * Страница регистрации.
     *
     * @return Response Inertia-страница Auth/Register
     */
    public function registerPage(): Response
    {
        return inertia('Auth/Register');
    }

    /**
     * Аутентификация пользователя по email и паролю.
     *
     * Регенерирует session ID и перенаправляет на dashboard.
     *
     * @param  LoginRequest     $request Валидированный запрос с credentials
     * @return RedirectResponse Редирект на маршрут dashboard
     *
     * @throws ValidationException при неверных credentials или rate limit
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $this->audit->log(
            auditAction: AuditAction::UserLoggedIn,
            entity: $request->user(),
            request: $request,
        );

        return redirect()->route('dashboard');
    }

    /**
     * Регистрация клиента: Customer + User, вход в сессию, редирект на dashboard.
     *
     * @param  RegisterRequest  $request     Валидированные данные регистрации (name, email, password)
     * @param  AuthService      $authService Сервис атомарного создания Customer и User
     * @return RedirectResponse Редирект на маршрут dashboard
     *
     * @throws Throwable при ошибке транзакции в AuthService
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

        $this->audit->log(
            auditAction: AuditAction::UserRegistered,
            entity: $result['user'],
            metadata: [
                'customer_uuid' => $result['customer']->uuid,
            ],
            request: $request,
        );

        return redirect()->route('dashboard');
    }

    /**
     * Выход из системы: audit log, invalidate session, редирект на login.
     *
     * Если пользователь аутентифицирован — пишет событие UserLoggedOut в audit log.
     *
     * @return RedirectResponse Редирект на маршрут login
     */
    public function logout(): RedirectResponse
    {
        $user = request()->user();

        if ($user !== null) {
            $this->audit->log(
                auditAction: AuditAction::UserLoggedOut,
                entity: $user,
                request: request(),
            );
        }

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
