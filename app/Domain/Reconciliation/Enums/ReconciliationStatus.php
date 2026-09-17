<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation\Enums;

/**
 * Статус результата сверки баланса счёта.
 *
 * - matched — баланс счёта совпадает с суммой проводок реестра;
 * - mismatched — обнаружено расхождение.
 *
 * @see ReconciliationReport
 */
enum ReconciliationStatus: string
{
    case Matched    = 'matched';
    case Mismatched = 'mismatched';
}
