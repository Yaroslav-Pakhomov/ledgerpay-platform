<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для добавления HTTP-заголовков безопасности.
 *
 * Заголовки уменьшают риск:
 *  - атак перехвата кликов (clickjacking);
 *  - принудительного определения MIME-типа (MIME-sniffing);
 *  - утечки информации Referer;
 *  - несанкционированного доступа к API браузера;
 *  - понижения версии протокола (downgrade) при использовании HTTPS.
 */
final class SecurityHeadersMiddleware
{
    /**
     * Обрабатывает входящий HTTP-запрос и добавляет
     * заголовки безопасности к исходящему ответу.
     *
     * @param  Request                    $request Текущий HTTP-запрос.
     * @param  Closure(Request): Response $next    Следующий middleware в цепочке.
     * @return Response                   HTTP-ответ с установленными заголовками безопасности.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Запрещает отображение приложения внутри <frame>, <iframe> и подобных
        // элементов, защищая от атак перехвата кликов.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Запрещает браузеру самостоятельно определять MIME-тип содержимого,
        // если он отличается от указанного Content-Type.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Для запросов между разными origin передаётся только origin,
        // а полный URL referrer сохраняется для запросов внутри одного origin.
        $response->headers->set(
            'Referrer-Policy', 'strict-origin-when-cross-origin'
        );

        // Запрещает использование указанных API браузера
        // страницей и встроенными в неё фреймов (iframe).
        $response->headers->set(
            'Permissions-Policy', 'camera=(), microphone=(), geolocation=()'
        );

        // HSTS сообщает браузеру, что сайт необходимо открывать
        // исключительно по HTTPS в течение указанного периода.
        // Заголовок отправляется только для HTTPS-запросов.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security', 'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
