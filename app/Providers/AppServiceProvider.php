<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Outbox\Contracts\IKafkaMessageProducer;
use App\Application\Outbox\Services\KafkaMessageProducer;
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
    }
}
