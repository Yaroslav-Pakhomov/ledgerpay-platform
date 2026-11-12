<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Tracing\Services\TracingService;
use App\Application\Tracing\Support\HttpSpanNameResolver;
use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * HTTP-прослойка серверного сегмента трассировки (OpenTelemetry, W3C Trace Context).
 *
 * Для каждого запроса создаёт серверный сегмент через {@see TracingService::withinSpan}
 * с типом {@see SpanKind::KIND_SERVER}: при непустом заголовке `traceparent` — родитель из
 * входящего W3C-носителя (недоверенный ввод), иначе новая или продолженная трассировка.
 * Зеркалирует идентификаторы в контекст Laravel на время обработки конвейера.
 *
 * После ответа: атрибуты `http.request.method` и `http.response.status_code`; при коде ≥ 500 —
 * статус сегмента ERROR. В заголовок ответа добавляется `X-Trace-Id` (`trace_id`) для диагностики;
 * единственным источником родителя остаётся `traceparent`, не произвольный client id.
 *
 * Имя сегмента и `http.route` / `laravel.route.name` — {@see HttpSpanNameResolver::resolve()}.
 * Регистрация: глобальный HTTP-стек в {@see bootstrap/app.php}.
 */
final readonly class TraceHttpRequestMiddleware
{
    /**
     * @param TracingService       $tracing          Сегменты и распространение контекста
     * @param HttpSpanNameResolver $spanNameResolver Имя SERVER-сегмента и атрибуты маршрута
     */
    public function __construct(
        private TracingService $tracing,
        private HttpSpanNameResolver $spanNameResolver,
    ) {}

    /**
     * Оборачивает обработку запроса в серверный сегмент и пробрасывает трассировку в ответ.
     *
     * @param Request                    $request Входящий HTTP-запрос; из заголовка читается `traceparent`
     * @param Closure(Request): Response $next    Следующий обработчик конвейера
     *
     * @throws Throwable Пробрасывает исключения из конвейера и из {@see TracingService::withinSpan}
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = [];

        // Родитель только из W3C traceparent, не из произвольного client id (ADR-016).
        $traceparent = $request->headers->get('traceparent');
        if (is_string($traceparent) && $traceparent !== '') {
            $incoming['traceparent'] = $traceparent;
        }

        [$spanName, $routeAttributes] = $this->spanNameResolver->resolve($request);

        return $this->tracing->withinSpan(
            name: $spanName,
            callback: function (SpanInterface $span) use ($request, $next, $routeAttributes): Response {
                foreach ($routeAttributes as $key => $value) {
                    if (!empty($key)) {
                        $span->setAttribute($key, $value);
                    }
                }

                // Сегмент живёт на весь конвейер: controller, БД, job dispatch внутри того же HTTP.
                $response = $next($request);

                $span->setAttribute('http.request.method', $request->method());
                $span->setAttribute('http.response.status_code', $response->getStatusCode());

                if ($response->getStatusCode() >= 500) {
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }

                // X-Trace-Id для поддержки; цепочка в Jaeger — по traceparent / Context.
                $traceId = $this->tracing->currentTraceId();

                if ($traceId !== null) {
                    $response->headers->set('X-Trace-Id', $traceId);
                }

                return $response;
            },
            incoming: $incoming,
            kind: SpanKind::KIND_SERVER,
        );
    }
}
