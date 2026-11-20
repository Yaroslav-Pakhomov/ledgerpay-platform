<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Inbox\Services\InboxReceiverService;
use App\Domain\Customer\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonException;
use Tests\TestCase;

final class BackofficeInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_backoffice_user_can_open_inbox_page(): void
    {
        $this->actingAs(User::factory()->backOffice()->create());

        $this->get('/backoffice/inbox')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Backoffice/Inbox'));
    }

    public function test_customer_cannot_open_inbox_page(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->forCustomer($customer)->create());

        $this->get('/backoffice/inbox')->assertForbidden();
    }

    /**
     * @throws JsonException
     */
    public function test_inbox_list_renders_unprocessed_messages(): void
    {
        $this->actingAs(User::factory()->backOffice()->create());

        app(InboxReceiverService::class)->receive(
            externalMessageId: 'evt-backoffice-list',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $this->get('/backoffice/inbox')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backoffice/Inbox')
                ->has('messages.data', 1)
                ->where('messages.data.0.status', 'received')
                ->where('messages.data.0.processed_at', null));
    }
}
