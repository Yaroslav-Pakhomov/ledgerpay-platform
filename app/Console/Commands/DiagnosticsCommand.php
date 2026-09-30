<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Diagnostics\Services\DiagnosticsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;

/**
 * Artisan-команда: диагностика готовности в stdout (JSON).
 *
 * Вызывает {@see DiagnosticsService::readinessPayload()}.
 * Код выхода FAILURE при сводном статусе `failed` — удобно для планировщика и алертов.
 */
#[Signature('diagnostics:run')]
#[Description('Запуск диагностики готовности приложения.')]
final class DiagnosticsCommand extends Command
{
    /**
     * @return int Command::SUCCESS|Command::FAILURE
     *
     * @throws JsonException
     */
    public function handle(DiagnosticsService $diagnosticsService): int
    {
        $payload = $diagnosticsService->readinessPayload();

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $payload['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
