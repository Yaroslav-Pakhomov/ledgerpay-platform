<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Services;

use App\Application\Diagnostics\Checks\DatabaseConnectionCheck;
use App\Application\Diagnostics\Checks\FailedTransactionsCheck;
use App\Application\Diagnostics\Checks\IDiagnosticCheck;
use App\Application\Diagnostics\Checks\OutboxBacklogCheck;
use App\Application\Diagnostics\Checks\QueueBacklogCheck;
use App\Application\Diagnostics\Checks\RedisConnectionCheck;
use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use App\Console\Commands\DiagnosticsCommand;

/**
 * Сервис диагностики готовности приложения.
 *
 * Запускает набор проверок ({@see IDiagnosticCheck}): БД, Redis, очередь, outbox, неуспешные транзакции
 * и агрегирует результат в `ok` | `warning` | `failed`.
 * Используется публичным API `/api/v1/health/ready`, бэк-офисом и {@see DiagnosticsCommand}.
 */
final readonly class DiagnosticsService
{
    public function __construct(
        private DatabaseConnectionCheck $database,
        private RedisConnectionCheck $redis,
        private QueueBacklogCheck $queueBacklog,
        private OutboxBacklogCheck $outboxBacklog,
        private FailedTransactionsCheck $failedTransactions,
    ) {}

    /**
     * @return array<int, DiagnosticCheckResult>
     */
    public function runReadinessChecks(): array
    {
        return [
            $this->database->run(),
            $this->redis->run(),
            $this->queueBacklog->run(),
            $this->outboxBacklog->run(),
            $this->failedTransactions->run(),
        ];
    }

    /**
     * Структура ответа для API, CLI и Inertia.
     *
     * @return array{
     *     status: 'ok'|'warning'|'failed',
     *     checked_at: string,
     *     checks: list<array{name: string, status: string, message: string, context: array<string, mixed>}>
     * }
     */
    public function readinessPayload(): array
    {
        $checks = $this->runReadinessChecks();

        $hasFailed = collect($checks)
            ->contains(fn (DiagnosticCheckResult $check) => $check->status === 'failed');

        $hasWarning = collect($checks)
            ->contains(fn (DiagnosticCheckResult $check) => $check->status === 'warning');

        return [
            'status'     => $hasFailed ? 'failed' : ($hasWarning ? 'warning' : 'ok'),
            'checked_at' => now()->toISOString(),
            'checks'     => array_map(
                fn (DiagnosticCheckResult $check) => $check->toArray(),
                $checks,
            ),
        ];
    }
}
