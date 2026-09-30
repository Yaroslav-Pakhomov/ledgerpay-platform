<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Application\Diagnostics\Services\DiagnosticsService;
use App\Http\Controllers\Controller;
use Inertia\Response;

/**
 * Бэк-офис: страница операционной диагностики.
 *
 * GET `/backoffice/diagnostics` — результат проверок готовности и конфигурация среды.
 * Доступ только через middleware `backoffice`.
 */
final class DiagnosticsController extends Controller
{
    /**
     * Inertia-страница `Backoffice/Diagnostics` с результатами проверок.
     */
    public function __invoke(DiagnosticsService $diagnosticsService): Response
    {
        return inertia('Backoffice/Diagnostics', [
            'diagnostics' => $diagnosticsService->readinessPayload(),
            'runtime'     => [
                'laravel_version'     => app()->version(),
                'php_version'         => PHP_VERSION,
                'app_env'             => app()->environment(),
                'queue_connection'    => config('queue.default'),
                'cache_store'         => config('cache.default'),
                'database_connection' => config('database.default'),
            ],
        ]);
    }
}
