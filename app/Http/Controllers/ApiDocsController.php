<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Контроллер интерактивной API-документации (Swagger UI).
 *
 * Отдаёт HTML-страницу Swagger UI и canonical OpenAPI spec из
 * {@see config('api-docs.openapi_path')}. Spec не генерируется из PHP —
 * source of truth: `docs/openapi/ledgerpay.openapi.yaml`.
 *
 * Доступ управляется конфигом {@see config('api-docs')}:
 * - `API_DOCS_ENABLED` — master switch;
 * - `API_DOCS_LOCAL_ONLY` — 404 вне окружения `local`.
 *
 * Маршруты: `routes/web.php` → `/api/docs`, `/api/docs/openapi.yaml`.
 */
final class ApiDocsController extends Controller
{
    /**
     * Swagger UI (CDN) — страница с Try it out и Bearer auth.
     */
    public function ui(): View
    {
        $this->ensureEnabled();

        return view('api-docs.swagger');
    }

    /**
     * Отдаёт OpenAPI YAML для Swagger UI и внешних клиентов.
     */
    public function spec(): Response
    {
        $this->ensureEnabled();

        $path = config('api-docs.openapi_path');

        abort_unless(is_readable($path), HttpResponse::HTTP_NOT_FOUND);

        return response(
            file_get_contents($path),
            HttpResponse::HTTP_OK,
            ['Content-Type' => 'application/yaml; charset=utf-8'],
        );
    }

    /**
     * Проверяет, разрешена ли документация в текущем окружении.
     *
     * @throws NotFoundHttpException docs выключены или не local при local_only
     */
    private function ensureEnabled(): void
    {
        $enabled = config('api-docs.enabled');

        if (config('api-docs.local_only')) {
            $enabled = $enabled && app()->environment('local');
        }

        abort_unless($enabled, HttpResponse::HTTP_NOT_FOUND);
    }
}
