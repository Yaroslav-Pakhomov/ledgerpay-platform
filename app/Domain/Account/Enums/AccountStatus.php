<?php

declare(strict_types=1);

namespace App\Domain\Account\Enums;

/**
 * Статус банковского счета.
 *
 * Используется для контроля доступности операций:
 * - active — счет доступен для операций;
 * - blocked — операции временно запрещены;
 * - closed — счет закрыт и недоступен.
 *
 * Enum позволяет централизованно описать
 * допустимые состояния Account domain.
 */
enum AccountStatus: string
{
    case Active  = 'active';
    case Blocked = 'blocked';
    case Closed  = 'closed';
}
