<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Обрабатывает входящий HTTP-запрос.
 *
 * Проверяет, что пользователь авторизован и имеет права бэк-офиса.
 * Если проверка не пройдена, возвращает ошибку 403 Forbidden.
 *
 * @param Closure(Request): Response $next Следующий middleware или обработчик запроса
 */
final class EnsureBackofficeUser
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || !$user->isBackOffice()) {
            abort(Response::HTTP_FORBIDDEN, 'Требуется доступ в бэк-офис.');
        }

        return $next($request);
    }
}
