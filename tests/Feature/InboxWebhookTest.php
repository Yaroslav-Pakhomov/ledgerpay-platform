<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InboxWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inbox.token', str_repeat('s', 48));
        config()->set('inbox.source', 'demo-provider');
    }

    public function test_webhook_requires_authentication(): void
    {
        $this->postJson('/api/v1/integrations/inbox/events', [
            'external_message_id' => 'evt-001',
            'event_name'          => 'external.ping',
            'payload'             => ['ping' => true],
        ])->assertForbidden();
    }

    public function test_authenticated_webhook_returns_accepted(): void
    {
        $this->withToken(str_repeat('s', 48))
            ->postJson('/api/v1/integrations/inbox/events', [
                'external_message_id' => 'evt-002',
                'event_name'          => 'external.ping',
                'payload'             => ['ping' => true],
            ])
            ->assertAccepted()
            ->assertJsonPath('data.external_message_id', 'evt-002');
    }

    public function test_webhook_conflict_returns_409(): void
    {
        $token = str_repeat('s', 48);

        $this->withToken($token)->postJson('/api/v1/integrations/inbox/events', [
            'external_message_id' => 'evt-conflict',
            'event_name'          => 'external.ping',
            'payload'             => ['value' => 1],
        ])->assertAccepted();

        $this->withToken($token)->postJson('/api/v1/integrations/inbox/events', [
            'external_message_id' => 'evt-conflict',
            'event_name'          => 'external.ping',
            'payload'             => ['value' => 2],
        ])->assertConflict();
    }

    public function test_webhook_validation_requires_payload(): void
    {
        $this->withToken(str_repeat('s', 48))
            ->postJson('/api/v1/integrations/inbox/events', [
                'external_message_id' => 'evt-invalid',
                'event_name'          => 'external.ping',
            ])
            ->assertUnprocessable();
    }
}
