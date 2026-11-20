<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Inbox\Exceptions\InboxConflictException;
use App\Application\Inbox\Services\InboxReceiverService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inbox\Api\StoreInboxEventRequest;
use App\Support\Http\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Публичный webhook приёма внешних событий (inbox), без `auth:sanctum`.
 *
 * POST `/api/v1/integrations/inbox/events`
 * + middleware {@see AuthenticateInboxProvider}, `throttle:api-global`.
 *
 * Принимает JSON, сохраняет через {@see InboxReceiverService}.
 * Обработка асинхронно: {@see DispatchPendingInboxMessagesCommand} → {@see ProcessInboxMessageJob}.
 *
 * Ответы:
 * - 202 — принято (идемпотентный повтор с тем же содержимым тоже 202);
 * - 409 — {@see InboxConflictException} (тот же external id, другое содержимое);
 * - 403/413 — middleware; 422 — валидация.
 *
 * `source` берётся из {@see config('inbox.source')}, не из тела запроса.
 * В headers inbox сохраняется `traceparent` из {@see Context}, если есть.
 */
final class InboxWebhookController extends Controller
{
    /**
     * Валидация тела, приём события, краткий срез статуса в ответе.
     */
    public function __invoke(StoreInboxEventRequest $request, InboxReceiverService $receiver): JsonResponse
    {
        $validated = $request->validated();

        $source = (string) config('inbox.source', 'demo-provider');

        $headers     = [];
        $traceparent = Context::get('traceparent');

        if (is_string($traceparent)) {
            $headers['traceparent'] = $traceparent;
        }

        try {
            $message = $receiver->receive(
                externalMessageId: $validated['external_message_id'],
                source: $source,
                eventName: $validated['event_name'],
                payload: $validated['payload'],
                headers: $headers,
            );
        } catch (InboxConflictException) {
            return ProblemDetails::make(
                request: $request,
                title: 'Conflict',
                detail: 'Event ID already exists with different content.',
                status: Response::HTTP_CONFLICT,
            );
        } catch (JsonException) {
            return ProblemDetails::make(
                request: $request,
                title: 'Unprocessable Content',
                detail: 'Invalid JSON in inbox payload.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return response()->json([
            'data' => [
                'uuid'                => $message->uuid,
                'external_message_id' => $message->external_message_id,
                'source'              => $message->source,
                'event_name'          => $message->event_name,
                'status'              => $message->status->value,
            ],
        ], 202);
    }
}
