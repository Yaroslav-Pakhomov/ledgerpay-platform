<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Checks;

use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use Throwable;

/**
 * Проверка готовности: очередь transactional outbox ({@see OutboxMessage}).
 *
 * `warning`, если есть сообщения со статусом failed или ожидающих (pending) > 500.
 */
final class OutboxBacklogCheck implements IDiagnosticCheck
{
    public function run(): DiagnosticCheckResult
    {
        try {
            $pending = OutboxMessage::query()->where('status', OutboxStatus::Pending)->count();

            $failed = OutboxMessage::query()->where('status', OutboxStatus::Failed)->count();

            $status = $failed > 0 || $pending > 500 ? 'warning' : 'ok';

            return new DiagnosticCheckResult(
                name: 'outbox_backlog',
                status: $status,
                message: 'Проверен backlog outbox.',
                context: [
                    'pending' => $pending,
                    'failed'  => $failed,
                ],
            );
        } catch (Throwable $exception) {
            return new DiagnosticCheckResult(
                name: 'outbox_backlog',
                status: 'failed',
                message: $exception->getMessage(),
            );
        }
    }
}
