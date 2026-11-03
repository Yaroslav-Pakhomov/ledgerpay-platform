<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Посредник для GET /metrics (spatie/laravel-prometheus).
 *
 * Требует заголовок `Authorization: Bearer <секрет>` из
 * {@see config('ledgerpay_metrics.token')} (не короче 32 символов).
 * Иначе HTTP 403. Для бэк-офиса не используется.
 */
final class AuthorizeMetrics
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('ledgerpay_metrics.token');

        $actual = $request->header('Authorization', '');

        if (!is_string($expected) || strlen($expected) < 32 || !hash_equals('Bearer ' . $expected, $actual)) {
            abort(403);
        }

        return $next($request);
    }
}
