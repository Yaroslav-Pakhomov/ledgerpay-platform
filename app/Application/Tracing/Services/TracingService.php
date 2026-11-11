<?php

declare(strict_types=1);

namespace App\Application\Tracing\Services;

use App\Application\Tracing\Middleware\TraceQueuedJob;
use App\Http\Middleware\TraceHttpRequestMiddleware;
use App\Support\Http\ProblemDetails;
use Closure;
use Illuminate\Support\Facades\Context as LaravelContext;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

/**
 * Сервис приложения для распределённой трассировки (OpenTelemetry, W3C Trace Context).
 *
 * Централизует создание сегментов трассировки, распространение контекста {@see TraceContextPropagator} и зеркалирование
 * идентификаторов в Laravel {@see LaravelContext} (`trace_id`, `span_id`, `traceparent`)
 * для журнала аудита, логов и постановки задач в очередь без утечки контекста между несвязанными операциями.
 *
 * Используется из {@see TraceHttpRequestMiddleware} (серверный сегмент),
 * {@see TraceQueuedJob} (сегмент потребителя) и точечно
 * в задачах очереди и при публикации из исходящего ящика.
 * Имя трейсера: `ledgerpay`. Сбой экспортёра не влияет на бизнес-логику внутри обратного вызова.
 */
final class TracingService
{
    /**
     * Выполняет обратный вызов внутри активного сегмента с восстановлением предыдущего контекста Laravel.
     *
     * При непустом `$incoming` извлекает удалённого родителя (обычно ключ `traceparent`); иначе —
     * дочерний сегмент от текущего контекста OpenTelemetry или новый корневой сегмент.
     * Исключения записываются в сегмент (`recordException`, статус ERROR) и пробрасываются дальше.
     *
     * @param non-empty-string                               $name       Имя сегмента (например, `HTTP GET /api/...`)
     * @param Closure(SpanInterface): mixed                  $callback   Тело операции; сегмент доступен для атрибутов
     * @param array<string, string>|null                     $incoming   W3C-носитель (`traceparent` и др.); `null` — без извлечения
     * @param 0|1|2|3|4                                      $kind       {@see SpanKind}: INTERNAL — внутренний, SERVER — серверный, CONSUMER — потребитель, …
     * @param array<non-empty-string, bool|int|float|string> $attributes Атрибуты сегмента до вызова обратного вызова (без секретов и персональных данных)
     *
     * @throws Throwable Пробрасывает исключение из `$callback` после записи в сегмент
     */
    public function withinSpan(string $name, Closure $callback, ?array $incoming = null, int $kind = SpanKind::KIND_INTERNAL, array $attributes = []): mixed
    {
        // Трейсер 'ledgerpay' — все сегменты приложения в одной «семье» в Jaeger.
        $tracer = Globals::tracerProvider()->getTracer('ledgerpay');

        $builder = $tracer->spanBuilder($name)->setSpanKind($kind);

        // Связать с родителем (HTTP traceparent, очередь и т.д.); пусто — продолжить текущий OpenTelemetry-контекст.
        if ($incoming !== null && $incoming !== []) {
            $parent = TraceContextPropagator::getInstance()->extract($incoming);
            $builder->setParent($parent);
        }

        $span = $builder->startSpan();
        // activate() делает этот сегмент «текущим» для вложенных withinSpan и getCurrent().
        $scope = $span->activate();

        // Сохраняем Laravel Context, чтобы не затирать чужой trace после вложенных вызовов.
        $previous = [
            'trace_id'    => LaravelContext::get('trace_id'),
            'span_id'     => LaravelContext::get('span_id'),
            'traceparent' => LaravelContext::get('traceparent'),
        ];

        try {
            $context = $span->getContext();

            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }

            // Дубль в Laravel Context — audit, логи, dispatch() очереди без повторного inject.
            LaravelContext::add('trace_id', $context->getTraceId());
            LaravelContext::add('span_id', $context->getSpanId());
            LaravelContext::add('traceparent', $this->currentTraceparent());

            return $callback($span);
        } catch (Throwable $exception) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

            throw $exception;
        } finally {
            // end + detach обязательны: иначе утечка контекста между запросами/jobs.
            $span->end();
            $scope->detach();

            foreach ($previous as $key => $value) {
                if ($value === null) {
                    LaravelContext::forget($key);
                } else {
                    LaravelContext::add($key, $value);
                }
            }
        }
    }

    /**
     * Сериализует текущий контекст OpenTelemetry в W3C-носитель для передачи через границы процессов.
     *
     * @return array<string, string> Носитель с ключом `traceparent` при валидном текущем сегменте
     */
    public function injectCurrentContext(): array
    {
        $carrier = [];
        TraceContextPropagator::getInstance()->inject($carrier);

        return $carrier;
    }

    /**
     * Восстанавливает входящий носитель из контекста Laravel (после HTTP-прослойки или постановки задачи в очередь).
     *
     * @return array<string, string> `['traceparent' => …]` или пустой массив, если значение отсутствует
     */
    public function incomingFromLaravelContext(): array
    {
        $traceparent = LaravelContext::get('traceparent');

        if (!is_string($traceparent) || $traceparent === '') {
            return [];
        }

        return ['traceparent' => $traceparent];
    }

    /**
     * Идентификатор трассировки (`trace_id`, 32 hex) из контекста активного сегмента;
     * для сопоставления в журнале аудита и в ответе {@see ProblemDetails}.
     *
     * @return string|null `null`, если контекст текущего сегмента невалиден
     */
    public function currentTraceId(): ?string
    {
        $context = Span::getCurrent()->getContext();

        if (!$context->isValid()) {
            return null;
        }

        return $context->getTraceId();
    }

    /**
     * Идентификатор сегмента (`span_id`, 16 hex) из контекста активного сегмента.
     *
     * @return string|null `null`, если контекст текущего сегмента невалиден
     */
    public function currentSpanId(): ?string
    {
        $context = Span::getCurrent()->getContext();

        if (!$context->isValid()) {
            return null;
        }

        return $context->getSpanId();
    }

    /**
     * Значение заголовка W3C `traceparent` для текущего контекста.
     *
     * @return string|null `null`, если внедрение контекста не вернуло `traceparent`
     */
    public function currentTraceparent(): ?string
    {
        return $this->injectCurrentContext()['traceparent'] ?? null;
    }

    /**
     * Проверяет, что из носителя извлекается валидный удалённый контекст трассировки (недоверенный вход).
     *
     * @param array<string, string> $carrier W3C-носитель (как минимум `traceparent`)
     */
    public function isValidRemoteContext(array $carrier): bool
    {
        $context = TraceContextPropagator::getInstance()->extract($carrier);

        return Span::fromContext($context)->getContext()->isValid();
    }

    /*
     * Типовая цепочка: HTTP (SERVER) → queue (CONSUMER) → withinSpan в job (INTERNAL)
     * → outbox headers → PublishOutboxMessageJob (CONSUMER + traceparent).
     * Один trace_id на всю цепочку, если везде передан traceparent / Context.
     */
}
