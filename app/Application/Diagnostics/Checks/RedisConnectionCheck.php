<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Checks;

use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Проверка готовности: доступность Redis (PING).
 *
 * При ошибке подключения возвращает статус `failed`.
 */
final class RedisConnectionCheck implements IDiagnosticCheck
{
    public function run(): DiagnosticCheckResult
    {
        try {
            $pong = Redis::connection()->ping();

            return new DiagnosticCheckResult(
                name: 'redis',
                status: 'ok',
                message: 'Подключение к Redis доступно.',
                context: [
                    'ping' => $pong,
                ],
            );
        } catch (Throwable $exception) {
            return new DiagnosticCheckResult(
                name: 'redis',
                status: 'failed',
                message: $exception->getMessage(),
            );
        }
    }
}
