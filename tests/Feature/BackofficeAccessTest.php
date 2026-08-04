<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Customer\Models\Customer;
use App\Http\Middleware\EnsureBackofficeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-тесты доступа к разделу бэк-офиса.
 *
 * Маршруты `/backoffice/*` защищены цепочкой middleware:
 * `auth` — требует сессию, гостя перенаправляет на login;
 * `backoffice` ({@see EnsureBackofficeUser}) —
 * пропускает только пользователей с `customer_id === null` ({@see User::isBackOffice()}).
 *
 * Тестируется точка входа `GET /backoffice` (dashboard) как репрезентативный
 * сценарий для всей backoffice-группы маршрутов.
 */
final class BackofficeAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Backoffice-пользователь (`User::factory()->backOffice()`) получает доступ к dashboard.
     */
    public function test_backoffice_user_can_open_backoffice_dashboard(): void
    {
        $user = User::factory()->backOffice()->create();

        $this->actingAs($user);

        $this->get('/backoffice')
            ->assertOk();
    }

    /**
     * Клиентский пользователь авторизован, но не имеет прав бэк-офиса — ответ 403 Forbidden.
     */
    public function test_customer_user_cannot_open_backoffice_dashboard(): void
    {
        $customer = Customer::factory()->create();

        $user = User::factory()->forCustomer($customer)->create();

        $this->actingAs($user);

        $this->get('/backoffice')
            ->assertForbidden();
    }

    /**
     * Неавторизованный гость перенаправляется на страницу входа middleware `auth`,
     * до `EnsureBackofficeUser` запрос не доходит.
     */
    public function test_guest_is_redirected_from_backoffice_dashboard(): void
    {
        $this->get('/backoffice')
            ->assertRedirect(route('login'));
    }
}
