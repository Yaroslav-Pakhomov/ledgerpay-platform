<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * Направление ledger-проводки.
 *
 * В double-entry accounting каждая операция
 * создает debit/credit записи.
 *
 * Используется для:
 * - аудита движения средств;
 * - построения финансового ledger;
 * - анализа балансов.
 */
enum LedgerDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
