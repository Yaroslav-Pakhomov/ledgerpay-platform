<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Измеряет время выполнения запроса и создаёт структурированную запись
 *
 * Сначала появляется request_id (RequestIdMiddleware), затем он используется в логировании.
 */
class ApiRequestLoggingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        Log::info('API request handled.', [
            'request_id'  => $request->headers->get('X-Request-Id'),
            'method'      => $request->method(),
            'path'        => $request->path(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip'          => $request->ip(),
        ]);

        return $response;
    }
}
