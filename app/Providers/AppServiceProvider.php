<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Outbox\Contracts\IKafkaMessageProducer;
use App\Application\Outbox\Services\KafkaMessageProducer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов приложения.
     */
    #[\Override]
    public function register(): void
    {
        // Outbox Kafka transport: final KafkaMessageProducer → интерфейс для DI и unit-тестов.
        $this->app->bind(IKafkaMessageProducer::class, KafkaMessageProducer::class);

        if (
            $this->app->environment('local') &&
            class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)
        ) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Инициализация сервисов приложения.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        $this->configureRateLimiting();
    }

    /**
     * Настраивает именованные ограничения частоты запросов для API.
     *
     * Лимиты разделяются по пользователю или IP-адресу в зависимости от контекста:
     * - api-global — общий лимит для API;
     * - auth — защита маршрутов аутентификации;
     * - money-movement — более строгие лимиты для финансовых операций;
     * - backoffice-heavy — ограничение ресурсоёмких операций backoffice.
     */
    private function configureRateLimiting(): void
    {
        // Общий лимит API:
        // авторизованные пользователи ограничиваются по идентификатору пользователя,
        // неавторизованные — по IP-адресу.
        RateLimiter::for('api-global', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(120)
                ->by($userId ? 'user:' . $userId : 'ip:' . $request->ip());
        });

        // Лимит для аутентификации.
        // Ключ формируется из email и IP, чтобы ограничивать
        // многократные попытки входа для конкретной учётной записи с одного адреса.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by(strtolower((string) $request->input('email')) . '|' . $request->ip()));

        // Лимиты для операций движения денежных средств.
        // Одновременно применяются краткосрочное ограничение в минуту
        // и долгосрочное ограничение в час.
        RateLimiter::for('money-movement', function (Request $request) {
            $userId = $request->user()?->id;

            return [
                // Не более 20 финансовых операций в минуту.
                Limit::perMinute(20)
                    ->by($userId ? 'money:user:' . $userId : 'money:ip:' . $request->ip()),

                // Не более 200 финансовых операций в час.
                Limit::perHour(200)
                    ->by($userId ? 'money-hour:user:' . $userId : 'money-hour:ip:' . $request->ip()),
            ];
        });

        // Лимит для ресурсоёмких операций backoffice.
        // Ограничение применяется отдельно для каждого авторизованного пользователя.
        RateLimiter::for('backoffice-heavy', fn (Request $request) => Limit::perMinute(30)
            ->by('backoffice:' . $request->user()?->id));
    }
}
