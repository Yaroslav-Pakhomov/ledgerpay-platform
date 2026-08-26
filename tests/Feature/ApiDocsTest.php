<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ApiDocsController;
use Tests\TestCase;

/**
 * Feature-тесты Swagger UI и OpenAPI spec endpoint.
 *
 * Проверяет {@see ApiDocsController}:
 * - GET /api/docs — HTML Swagger UI;
 * - GET /api/docs/openapi.yaml — YAML spec;
 * - 404 при `API_DOCS_ENABLED=false`;
 * - 404 при `API_DOCS_LOCAL_ONLY=true` вне local (phpunit: APP_ENV=testing).
 *
 * В setUp снимаем local_only, иначе тесты всегда получают 404.
 */
final class ApiDocsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'api-docs.enabled'    => true,
            'api-docs.local_only' => false,
        ]);
    }

    public function test_api_docs_ui_is_available_when_enabled(): void
    {
        $this->get('/api/docs')->assertOk()->assertSeeHtml('swagger-ui');
    }

    public function test_openapi_spec_returns_yaml(): void
    {
        $this->get('/api/docs/openapi.yaml')
            ->assertOk()->assertHeader('Content-Type', 'application/yaml; charset=utf-8')->assertSeeHtml('openapi: 3.0.3');
    }

    public function test_api_docs_hidden_when_disabled(): void
    {
        config(['api-docs.enabled' => false]);

        $this->get('/api/docs')->assertNotFound();
        $this->get('/api/docs/openapi.yaml')->assertNotFound();
    }

    public function test_api_docs_hidden_outside_local_when_local_only(): void
    {
        config([
            'api-docs.enabled'    => true,
            'api-docs.local_only' => true,
        ]);

        $this->get('/api/docs')->assertNotFound();
    }
}
