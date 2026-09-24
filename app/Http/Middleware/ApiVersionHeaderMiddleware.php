<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Добавляет заголовок X-API-Version ко всем ответам маршрутов из `routes/api.php`.
 *
 * Значения: `v1` для path `api/v1/*`, иначе `legacy` (deprecated aliases `/api/*` без v1).
 *
 * @see docs/api-versioning.md
 */
final class ApiVersionHeaderMiddleware
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

        $version = str_starts_with($request->path(), 'api/v1') ? 'v1' : 'legacy';

        $response->headers->set('X-API-Version', $version);

        return $response;
    }
}
