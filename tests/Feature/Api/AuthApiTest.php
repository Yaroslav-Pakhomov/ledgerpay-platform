<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'     => 'Alice_Morgan',
            'email'    => 'alice@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'user' => ['name', 'email'],
                'customer',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'alice@example.com',
        ]);

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_customer_can_login(): void
    {
        User::factory()->create([
            'email'    => 'alice@example.com',
            'password' => 'StrongPassword123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'       => 'alice@example.com',
            'password'    => 'StrongPassword123!',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'user',
            ]);
    }

    public function test_authenticated_user_can_fetch_me(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_guest_cannot_access_accounts(): void
    {
        $response = $this->getJson('/api/accounts');

        $response->assertUnauthorized();
    }
}
