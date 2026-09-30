<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Diagnostics\Services\DiagnosticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Публичные эндпоинты состояния для оркестраторов (без Sanctum).
 *
 * - GET `/api/v1/health/live` — жизнеспособность процесса;
 * - GET `/api/v1/health/ready` — готовность через {@see DiagnosticsService}.
 */
final class HealthController extends Controller
{
    /**
     * Жизнеспособность: приложение отвечает на HTTP.
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status'     => 'ok',
            'checked_at' => now()->toISOString(),
        ]);
    }

    /**
     * Готовность: сводный статус зависимостей.
     *
     * HTTP 503 только при сводном статусе `failed`; `warning` → 200.
     */
    public function ready(DiagnosticsService $diagnosticsService): JsonResponse
    {
        $payload = $diagnosticsService->readinessPayload();

        $httpStatus = $payload['status'] === 'failed' ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK;

        return response()->json($payload, $httpStatus);
    }
}
