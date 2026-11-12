<?php

declare(strict_types=1);

namespace App\Application\Tracing\Middleware;

use App\Application\Tracing\Services\TracingService;
use App\Application\Tracing\Support\SpanNames;
use Closure;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;

/**
 * Прослойка очереди: сегмент потребителя для задач Laravel (OpenTelemetry, W3C Trace Context).
 *
 * Подключается в {@see ProcessTransactionJob::middleware()} и {@see PublishOutboxMessageJob::middleware()}
 * и оборачивает выполнение задачи в {@see TracingService::withinSpan} с типом
 * {@see SpanKind::KIND_CONSUMER}. Имя сегмента задаётся параметром `$operation` (например,
 * {@see SpanNames::TRANSACTION_PROCESS}).
 *
 * Входящий контекст: при переданном `$traceparent` — W3C-носитель из свойств задачи (граница
 * scheduler/outbox); иначе — {@see TracingService::incomingFromLaravelContext()} (значение,
 * сохранённое при постановке в очередь после HTTP или родительской задачи).
 *
 * Атрибуты сегмента: `messaging.system` (имя соединения очереди), `messaging.operation.name` = `process`.
 * Сервис трассировки берётся из контейнера в {@see self::handle()}, т.к. прослойка сериализуется вместе с задачей.
 */
final readonly class TraceQueuedJob
{
    /**
     * @param non-empty-string $operation   Имя сегмента в трассировщике (стабильный идентификатор операции)
     * @param string|null      $traceparent Значение W3C `traceparent` с границы dispatch; `null` — из контекста Laravel
     */
    public function __construct(
        private string $operation,
        private ?string $traceparent = null,
    ) {}

    /**
     * Создаёт сегмент потребителя и выполняет следующие прослойки/обработчик задачи.
     *
     * @param  object                 $job  Экземпляр задачи очереди (Laravel передаёт задачу в цепочку middleware)
     * @param  Closure(object): mixed $next Продолжение цепочки прослоек очереди
     * @return mixed                  Результат `$next($job)`
     *
     * @throws Throwable Пробрасывает исключения из обработки задачи и из {@see TracingService::withinSpan}
     */
    public function handle(object $job, Closure $next): mixed
    {
        // Middleware сериализуется с job — DI в конструктор не используем.
        $tracing = app(TracingService::class);

        // Outbox: traceparent в свойстве job; иначе — то, что Laravel унёс при dispatch().
        $incoming = $this->traceparent !== null ? ['traceparent' => $this->traceparent] : $tracing->incomingFromLaravelContext();

        $queueConnection = (string) config('queue.default', 'redis');

        /** @var non-empty-string $operation */
        $operation = $this->operation;

        return $tracing->withinSpan(
            name: $operation,
            callback: static fn () => $next($job),
            incoming: $incoming,
            kind: SpanKind::KIND_CONSUMER, // в Jaeger этот шаг — «потребитель очереди», не HTTP.
            attributes: [
                'messaging.system'         => $queueConnection,
                'messaging.operation.name' => 'process',
            ],
        );
    }
}
