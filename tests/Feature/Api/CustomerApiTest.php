<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Customer\Enums\CustomerStatus;
use App\Domain\Customer\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_customer(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        $response = $this->postJson('/api/customers', [
            'name'  => 'Alice Morgan',
            'email' => 'alice@example.com',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Alice Morgan')
            ->assertJsonPath('data.email', 'alice@example.com')
            ->assertJsonPath('data.status', 'active');

        $this->assertNotEmpty($response->json('data.uuid'));

        $this->assertDatabaseHas('customers', [
            'email'  => 'alice@example.com',
            'status' => CustomerStatus::Active->value,
        ]);
    }

    public function test_cannot_create_customer_with_duplicate_email(): void
    {
        Customer::query()->create([
            'name'   => 'Existing User',
            'email'  => 'alice@example.com',
            'status' => CustomerStatus::Active,
        ]);

        $response = $this->postJson('/api/customers', [
            'name'  => 'Alice Morgan',
            'email' => 'alice@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_can_list_customers(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        Customer::query()->create([
            'name'   => 'Alice Morgan',
            'email'  => 'alice@example.com',
            'status' => CustomerStatus::Active,
        ]);

        $response = $this->getJson('/api/customers');

        $response->assertOk()
            ->assertJsonPath('data.0.email', 'alice@example.com');
    }

    public function test_can_show_customer_by_uuid(): void
    {
        /**
         * Для вывода ошибок при выполнении текущего теста
         */
        $this->withoutExceptionHandling();

        $customer = Customer::query()->create([
            'name'   => 'Alice Morgan',
            'email'  => 'alice@example.com',
            'status' => CustomerStatus::Active,
        ]);

        $response = $this->getJson('/api/customers/'.$customer->uuid);

        $response->assertOk()
            ->assertJsonPath('data.uuid', $customer->uuid)
            ->assertJsonPath('data.email', 'alice@example.com');
    }

    public function test_returns_404_for_unknown_customer(): void
    {
        $response = $this->getJson('/api/customers/'.Str::uuid()->toString());

        $response->assertNotFound();
    }

    public function test_validates_store_customer_request(): void
    {
        $response = $this->postJson('/api/customers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }
}
