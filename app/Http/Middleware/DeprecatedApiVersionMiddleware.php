<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Добавляет заголовки deprecation к legacy-маршрутам `/api/*` (alias `api.deprecated`).
 *
 * Клиент получает: Deprecation, Sunset (RFC 7231), Link на successor-version `/api/v1`.
 *
 * @see docs/api-versioning.md
 */
final class DeprecatedApiVersionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', now()->addMonths(6)->toRfc7231String());
        $response->headers->set('Link', '</api/v1>; rel="successor-version"');

        return $response;
    }
}
