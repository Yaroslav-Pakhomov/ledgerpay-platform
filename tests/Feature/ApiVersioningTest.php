<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-тесты версионирования API: заголовки v1/legacy и доступность `/api/v1/*`.
 */
final class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_routes_use_v1_prefix(): void
    {
        $path = parse_url(route('api.accounts.store'), PHP_URL_PATH);

        $this->assertIsString($path);
        $this->assertStringStartsWith('/api/v1/', $path);
    }

    public function test_v1_api_returns_version_header(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertHeader('X-API-Version', 'v1');
    }

    public function test_legacy_api_returns_deprecation_headers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertHeader('X-API-Version', 'legacy')
            ->assertHeader('Deprecation', 'true')
            ->assertHeader('Link', '</api/v1>; rel="successor-version"');

        $this->assertNotEmpty($response->headers->get('Sunset'));
    }

    public function test_v1_register_route_exists(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'     => 'Alice Morgan',
            'email'    => 'alice.v1@example.com',
            'password' => 'StrongPassword123!',
        ])->assertCreated();
    }
}
