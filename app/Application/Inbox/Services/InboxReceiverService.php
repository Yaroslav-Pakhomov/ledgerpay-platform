<?php

declare(strict_types=1);

namespace App\Application\Inbox\Services;

use App\Application\Inbox\Exceptions\InboxConflictException;
use App\Domain\Inbox\Enums\InboxStatus;
use App\Domain\Inbox\Models\InboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

/**
 * Приём входящего inbox-сообщения: идемпотентная вставка и проверка конфликта.
 *
 * Идемпотентность по уникальному ключу (source, external_message_id):
 * {@see insertOrIgnore} — повторная доставка с тем же event_name и payload не создаёт дубль.
 *
 * Если запись уже есть, но отличаются event_name или SHA-256 канонического JSON
 * (event_name + payload), выбрасывается {@see InboxConflictException} → HTTP 409 в webhook.
 *
 * Не ставит jobs в очередь; dispatch — {@see DispatchPendingInboxMessagesCommand}.
 *
 * @see InboxWebhookController
 */
final class InboxReceiverService
{
    /**
     * Сохраняет сообщение в статусе {@see InboxStatus::Received} и возвращает модель.
     *
     * @param array<string, mixed> $payload Тело события из webhook
     * @param array<string, mixed> $headers Метаданные (например traceparent)
     *
     * @throws JsonException
     * @throws InboxConflictException
     */
    public function receive(
        string $externalMessageId,
        string $source,
        string $eventName,
        array $payload,
        array $headers = [],
    ): InboxMessage {
        $json = json_encode(
            [
                'event_name' => $eventName,
                'payload'    => $payload,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $hash = hash('sha256', $json);
        $now  = now();

        DB::table('inbox_messages')->insertOrIgnore([
            'uuid'                => (string) Str::uuid(),
            'source'              => $source,
            'external_message_id' => $externalMessageId,
            'event_name'          => $eventName,
            'payload_hash'        => $hash,
            'payload'             => json_encode($payload, JSON_THROW_ON_ERROR),
            'headers'             => json_encode($headers, JSON_THROW_ON_ERROR),
            'status'              => InboxStatus::Received->value,
            'attempts'            => 0,
            'received_at'         => $now,
            'available_at'        => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $message = InboxMessage::query()->where('source', $source)->where('external_message_id', $externalMessageId)->firstOrFail();

        if ($message->event_name !== $eventName || !hash_equals($message->payload_hash, $hash)) {
            throw new InboxConflictException;
        }

        return $message;
    }
}
