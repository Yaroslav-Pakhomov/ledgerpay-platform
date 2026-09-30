<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Checks;

use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use Throwable;

/**
 * Проверка готовности: количество терминальных неуспешных транзакций.
 *
 * Любая {@see Transaction} со статусом {@see TransactionStatus::Failed} → `warning` (не блокирует HTTP 503).
 */
final class FailedTransactionsCheck implements IDiagnosticCheck
{
    public function run(): DiagnosticCheckResult
    {
        try {
            $failed = Transaction::query()
                ->where('status', TransactionStatus::Failed)
                ->count();

            return new DiagnosticCheckResult(
                name: 'failed_transactions',
                status: $failed > 0 ? 'warning' : 'ok',
                message: 'Проверены неуспешные транзакции.',
                context: [
                    'failed' => $failed,
                ],
            );
        } catch (Throwable $exception) {
            return new DiagnosticCheckResult(
                name: 'failed_transactions',
                status: 'failed',
                message: $exception->getMessage(),
            );
        }
    }
}
