<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Tracing\Services\TracingService;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Logs\LoggerProviderFactory;
use OpenTelemetry\SDK\Metrics\MeterProviderFactory;
use OpenTelemetry\SDK\Propagation\PropagatorFactory;
use OpenTelemetry\SDK\Propagation\ResponsePropagatorFactory;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\ExporterFactory;
use OpenTelemetry\SDK\Trace\SamplerFactory;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use Override;

/**
 * Ручная инициализация OpenTelemetry после bootstrap Laravel (ADR-016).
 *
 * Не вызывает {@see SdkAutoloader} — без auto-instrumentation и без Event API.
 * Сегменты в приложении — {@see TracingService}.
 * `OTEL_*` — env процесса ({@see Variables}, compose / phpunit / `.env`).
 *
 * {@see Globals::registerInitializer} — внутренний API SDK (как в официальном autoload).
 */
final class OpenTelemetryServiceProvider extends ServiceProvider
{
    /**
     * Собирает SDK из env и регистрирует глобальный конфигуратор (ленивая инициализация при первом `Globals::tracerProvider()`).
     */
    #[Override]
    public function register(): void
    {
        if (!Configuration::getBoolean(Variables::OTEL_PHP_AUTOLOAD_ENABLED)) {
            return;
        }

        Globals::registerInitializer(static function (Configurator $configurator): Configurator {
            // W3C traceparent — нужен даже при OTEL_SDK_DISABLED (извлечение/внедрение контекста).
            $propagator         = new PropagatorFactory()->create();
            $responsePropagator = new ResponsePropagatorFactory()->create();

            if (Sdk::isDisabled()) {
                return $configurator->withPropagator($propagator)->withResponsePropagator($responsePropagator);
            }

            $resource = ResourceInfoFactory::defaultResource();

            $traceExporter = new ExporterFactory()->create();
            $meterProvider = new MeterProviderFactory()->create($resource);

            // Процессор: принимает завершённые сегменты и отдаёт экспортёру (OTLP → Jaeger).
            $spanProcessor  = new SpanProcessorFactory()->create($traceExporter);
            $tracerProvider = new TracerProviderBuilder()->addSpanProcessor($spanProcessor)->setResource($resource)->setSampler(new SamplerFactory()->create())->build();

            // При OTEL_LOGS_EXPORTER=none по сути noop; подключаем для полноты SDK.
            $loggerProvider = new LoggerProviderFactory()->create(null, $resource);

            // Сброс буферов при shutdown PHP (artisan, worker, FPM-запрос).
            ShutdownHandler::register($tracerProvider->shutdown(...));
            ShutdownHandler::register($meterProvider->shutdown(...));
            ShutdownHandler::register($loggerProvider->shutdown(...));

            return $configurator
                ->withTracerProvider($tracerProvider)
                ->withMeterProvider($meterProvider)
                ->withLoggerProvider($loggerProvider)
                ->withPropagator($propagator)
                ->withResponsePropagator($responsePropagator);
        });
    }
}
