<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Enums;

/**
 * Тип финансовой операции.
 *
 * Используется для определения бизнес-сценария:
 * - deposit — пополнение счета;
 * - withdrawal — вывод средств;
 * - transfer — перевод между счетами.
 *
 * Тип транзакции влияет на:
 * - обработку балансов;
 * - ledger-проводки;
 * - правила валидации.
 */
enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Transfer = 'transfer';
}
