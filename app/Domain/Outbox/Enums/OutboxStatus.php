<?php

declare(strict_types=1);

namespace App\Domain\Outbox\Enums;

use App\Console\Commands\DispatchPendingOutboxMessagesCommand;
use App\Domain\Outbox\Models\OutboxMessage;

/**
 * Статус публикации outbox-сообщения.
 *
 * Жизненный цикл:
 * - pending — записано в БД, ожидает dispatch в очередь;
 * - processing — worker взял сообщение в работу;
 * - published — успешно опубликовано (log / Kafka);
 * - failed — ошибка публикации, доступно для retry через {@see DispatchPendingOutboxMessagesCommand}.
 *
 * @see OutboxMessage
 */
enum OutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Published = 'published';
    case Failed = 'failed';
}
