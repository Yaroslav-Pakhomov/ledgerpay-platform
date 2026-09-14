<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Enums;

/**
 * Статус обработки транзакции.
 *
 * Позволяет отслеживать жизненный цикл операции:
 * - pending — создана, ожидает обработки;
 * - processing — выполняется;
 * - completed — успешно завершена;
 * - failed — завершилась ошибкой;
 * - cancelled — отменена.
 *
 * Используется для:
 * - идемпотентности;
 * - retry-механизмов;
 * - audit/debugging.
 */
enum TransactionStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';
}
