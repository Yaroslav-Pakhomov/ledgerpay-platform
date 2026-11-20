<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Domain\Inbox\Models\InboxMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Backoffice: мониторинг transactional inbox.
 *
 * GET `/backoffice/inbox` — список {@see InboxMessage} без raw payload.
 */
final class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();

        $messagesPaginator = InboxMessage::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->latest('id')
            ->cursorPaginate(50)
            ->withQueryString();

        $messages         = $messagesPaginator->toArray();
        $messages['data'] = collect($messagesPaginator->items())
            ->map(fn (InboxMessage $message): array => [
                'uuid'                => $message->uuid,
                'external_message_id' => $message->external_message_id,
                'source'              => $message->source,
                'event_name'          => $message->event_name,
                'status'              => $message->status->value,
                'attempts'            => $message->attempts,
                'last_error_code'     => $message->last_error_code,
                'received_at'         => $message->received_at->toDateTimeString(),
                'processed_at'        => $message->processed_at?->toDateTimeString(),
            ])
            ->all();

        return inertia('Backoffice/Inbox', [
            'filters' => [
                'status' => $status,
                'source' => $source,
            ],
            'messages' => $messages,
        ]);
    }
}
