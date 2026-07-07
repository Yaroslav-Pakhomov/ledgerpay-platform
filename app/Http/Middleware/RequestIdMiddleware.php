<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequestIdMiddleware проверяет заголовок:
 *
 * X-Request-Id
 *
 * Если клиент передал его, используется переданное значение. Если нет — генерируется UUID.
 *
 * Идентификатор:
 *
 *  - добавляется во входящий объект запроса;
 *  - помещается в Laravel Context;
 *  - должен добавляться в заголовок ответа;
 *  - включается в JSON ошибок и логи.
 *
 * Это позволяет найти все записи, относящиеся к конкретному запросу.
 */
class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id');

        if (!is_string($requestId) || trim($requestId) === '') {
            $requestId = (string) Str::uuid();
        }

        $request->headers->set('X-Request-Id', $requestId);

        /**
         * Сохраняем request_id в контексте текущего выполнения Laravel. После этого значение становится доступно в разных частях приложения без необходимости вручную передавать его через параметры методов.
         *
         * Главное назначение — связать все действия одного запроса общим идентификатором.
         *
         * Позже, в другом классе:
         * $requestId = Context::get('request_id');
         *
         * Все последующие записи в логах автоматически получают этот контекст
         */
        Context::add('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
