<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация входящего inbox-webhook (machine-to-machine, не Sanctum).
 *
 * Маршрут: POST `/api/v1/integrations/inbox/events` ({@see InboxWebhookController}).
 *
 * - сырое тело запроса не длиннее {@see config('inbox.max_payload_bytes')} → иначе HTTP 413;
 * - заголовок `Authorization: Bearer <секрет>` из {@see config('inbox.token')}
 *   (строка не короче 32 символов, сравнение через hash_equals) → иначе HTTP 403.
 */
final class AuthenticateInboxProvider
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('inbox.token');

        $maxBytes = (int) config('inbox.max_payload_bytes', 65536);

        if (strlen($request->getContent()) > $maxBytes) {
            abort(413);
        }

        if (
            !is_string($expected)
            || strlen($expected) < 32
            || !hash_equals('Bearer ' . $expected, $request->header('Authorization', ''))
        ) {
            abort(403);
        }

        return $next($request);
    }
}
