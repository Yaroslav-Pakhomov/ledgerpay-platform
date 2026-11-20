<?php

declare(strict_types=1);

namespace App\Application\Inbox\Exceptions;

use RuntimeException;

/**
 * Повторная доставка с тем же (source, external_message_id), но другим содержимым.
 *
 * Возникает в {@see InboxReceiverService::receive()}, когда существующая строка
 * в inbox_messages не совпадает по event_name или payload_hash с новым событием.
 * Отличается от идемпотентного повтора (тот же payload → возврат существующей записи).
 *
 * API: {@see InboxWebhookController} отвечает 409 Problem Details.
 */
final class InboxConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Событие с тем же источником и внешним идентификатором уже существует с другим содержимым.'
        );
    }
}
