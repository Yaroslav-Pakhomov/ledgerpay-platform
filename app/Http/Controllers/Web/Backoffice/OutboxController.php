<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Backoffice: мониторинг transactional outbox.
 *
 * GET `/backoffice/outbox` — paginated список {@see OutboxMessage}
 * с фильтрами по `status` и `event_name`. Доступ только через middleware `backoffice`.
 */
final class OutboxController extends Controller
{
    /**
     * Страница outbox-сообщений с фильтрами и пагинацией.
     *
     * Query-параметры:
     * - `status` — значение {@see OutboxStatus} (pending, processing, published, failed);
     * - `event_name` — имя доменного события (transaction.created, transaction.completed, transaction.failed).
     *
     * @param  Request  $request Query: status, event_name, page
     * @return Response Inertia-страница Backoffice/Outbox
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $eventName = $request->string('event_name')->toString();

        $messages = OutboxMessage::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($eventName !== '', fn ($query) => $query->where('event_name', $eventName))
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (OutboxMessage $message) => [
                'uuid'           => $message->uuid,
                'event_name'     => $message->event_name,
                'aggregate_type' => $message->aggregate_type,
                'aggregate_uuid' => $message->aggregate_uuid,
                'payload'        => $message->payload,
                'headers'        => $message->headers,
                'status'         => $message->status->value,
                'attempts'       => $message->attempts,
                'last_error'     => $message->last_error,
                'available_at'   => $message->available_at?->toDateTimeString(),
                'published_at'   => $message->published_at?->toDateTimeString(),
                'created_at'     => $message->created_at->toDateTimeString(),
            ]);

        return inertia('Backoffice/Outbox', [
            'filters' => [
                'status'     => $status,
                'event_name' => $eventName,
            ],
            'messages' => $messages,
        ]);
    }
}
