<?php

declare(strict_types=1);

namespace App\Domain\Customer\Enums;

/**
 * Статус клиента.
 *
 * В fintech-системах статус клиента влияет на доступность операций:
 * - active — клиент может открывать счета и выполнять операции;
 * - blocked — операции и создание новых счетов запрещены.
 *
 * Enum используется вместо строковых констант для:
 * - типобезопасности;
 * - читаемости доменной логики;
 * - уменьшения риска некорректных статусов.
 */
enum CustomerStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}
