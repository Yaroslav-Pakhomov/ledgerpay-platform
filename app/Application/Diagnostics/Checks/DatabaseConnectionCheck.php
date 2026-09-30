<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Checks;

use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Проверка готовности: доступность PostgreSQL (`SELECT 1`).
 *
 * При ошибке подключения возвращает статус `failed`.
 */
final class DatabaseConnectionCheck implements IDiagnosticCheck
{
    public function run(): DiagnosticCheckResult
    {
        try {
            $result = DB::selectOne('SELECT 1 as ok');

            return new DiagnosticCheckResult(
                name: 'database',
                status: $result?->ok === 1 ? 'ok' : 'failed',
                message: 'Подключение к PostgreSQL доступно.',
                context: [
                    'connection' => config('database.default'),
                ],
            );
        } catch (Throwable $exception) {
            return new DiagnosticCheckResult(
                name: 'database',
                status: 'failed',
                message: $exception->getMessage(),
                context: [
                    'connection' => config('database.default'),
                ],
            );
        }
    }
}
