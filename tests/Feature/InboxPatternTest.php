<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Inbox\Exceptions\InboxConflictException;
use App\Application\Inbox\Jobs\ProcessInboxMessageJob;
use App\Application\Inbox\Services\InboxProcessorService;
use App\Application\Inbox\Services\InboxReceiverService;
use App\Domain\Inbox\Enums\InboxStatus;
use App\Domain\Inbox\Models\InboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use JsonException;
use Tests\TestCase;

final class InboxPatternTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws JsonException
     */
    public function test_receiver_stores_message(): void
    {
        $message = app(InboxReceiverService::class)->receive(
            externalMessageId: 'evt-001',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $this->assertDatabaseHas('inbox_messages', [
            'id'                  => $message->id,
            'source'              => 'demo-provider',
            'external_message_id' => 'evt-001',
            'status'              => 'received',
        ]);
    }

    /**
     * @throws JsonException
     */
    public function test_duplicate_event_is_idempotent(): void
    {
        $receiver = app(InboxReceiverService::class);

        $first = $receiver->receive(
            externalMessageId: 'evt-duplicate',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $second = $receiver->receive(
            externalMessageId: 'evt-duplicate',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, InboxMessage::query()->count());
    }

    /**
     * @throws JsonException
     */
    public function test_same_id_with_different_payload_is_rejected(): void
    {
        $receiver = app(InboxReceiverService::class);

        $receiver->receive(
            externalMessageId: 'evt-conflict',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['value' => 1],
        );

        $this->expectException(InboxConflictException::class);

        $receiver->receive(
            externalMessageId: 'evt-conflict',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['value' => 2],
        );
    }

    /**
     * @throws JsonException
     */
    public function test_processor_executes_business_effect_once(): void
    {
        $message = app(InboxReceiverService::class)->receive(
            externalMessageId: 'evt-process-once',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $processor = app(InboxProcessorService::class);
        $processor->process($message->id);
        $processor->process($message->id);

        $this->assertSame(1, DB::table('inbox_ping_receipts')->where('inbox_message_id', $message->id)->count());

        $message->refresh();
        $this->assertSame(InboxStatus::Processed, $message->status);
        $this->assertSame(1, $message->attempts);
    }

    /**
     * @throws JsonException
     */
    public function test_dispatcher_enqueues_pending_message(): void
    {
        Queue::fake();

        app(InboxReceiverService::class)->receive(
            externalMessageId: 'evt-dispatch',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $this->runArtisanSuccessfully('inbox:dispatch-pending', ['--limit' => 10]);

        Queue::assertPushed(ProcessInboxMessageJob::class, 1);
    }

    /**
     * @throws JsonException
     */
    public function test_same_external_id_from_different_sources_is_allowed(): void
    {
        $receiver = app(InboxReceiverService::class);

        $receiver->receive(
            externalMessageId: 'evt-shared',
            source: 'provider-a',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $receiver->receive(
            externalMessageId: 'evt-shared',
            source: 'provider-b',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $this->assertSame(2, InboxMessage::query()->count());
    }

    /**
     * @throws JsonException
     */
    public function test_unknown_event_becomes_failed(): void
    {
        $message = app(InboxReceiverService::class)->receive(
            externalMessageId: 'evt-unknown',
            source: 'demo-provider',
            eventName: 'external.unknown',
            payload: ['value' => true],
        );

        app(InboxProcessorService::class)->process($message->id);

        $message->refresh();

        $this->assertSame(InboxStatus::Failed, $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertNull($message->processed_at);
    }

    /**
     * @throws JsonException
     */
    public function test_expired_dispatch_lease_is_recoverable(): void
    {
        Queue::fake();

        $message = app(InboxReceiverService::class)->receive(
            externalMessageId: 'evt-recover',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $message->update(['dispatch_lease_until' => now()->subMinute()]);

        $this->runArtisanSuccessfully('inbox:dispatch-pending', ['--limit' => 10]);

        Queue::assertPushed(ProcessInboxMessageJob::class, 1);
    }

    /**
     * @throws JsonException
     */
    public function test_terminal_message_is_not_processed_again(): void
    {
        $message = app(InboxReceiverService::class)->receive(
            externalMessageId: 'evt-terminal',
            source: 'demo-provider',
            eventName: 'external.ping',
            payload: ['ping' => true],
        );

        $processor = app(InboxProcessorService::class);
        $processor->process($message->id);

        $this->assertSame(InboxStatus::Processed, $message->fresh()->status);

        $processor->process($message->id);

        $this->assertSame(
            1,
            DB::table('inbox_ping_receipts')->where('inbox_message_id', $message->id)->count(),
        );
    }
}
