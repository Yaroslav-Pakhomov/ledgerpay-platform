<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Enums;

/**
 * Статус обработки inbox-сообщения (входящее событие от внешней интеграции).
 *
 * Жизненный цикл:
 * - received — принято webhook’ом и сохранено в БД ({@see InboxReceiverService});
 * - processing — worker выполняет обработчик ({@see InboxProcessorService});
 * - processed — обработчик успешно отработал, повторная обработка не требуется;
 * - failed — ошибка обработки; сообщение снова попадает в dispatch после {@see InboxProcessorService}
 *   (сдвиг {@see InboxMessage::$available_at} и экспоненциальный backoff);
 * - dead — исчерпаны попытки ({@see config('inbox.max_attempts')}), ручное вмешательство;
 * - ignored — терминальный статус «сознательно пропущено» (без retry); учитывается в {@see InboxMessage::isTerminal()}.
 *
 * Повторный dispatch в очередь: статусы received и failed при {@see DispatchPendingInboxMessagesCommand}.
 *
 * @see InboxMessage
 */
enum InboxStatus: string
{
    case Received   = 'received';
    case Processing = 'processing';
    case Processed  = 'processed';
    case Failed     = 'failed';
    case Dead       = 'dead';
    case Ignored    = 'ignored';
}
