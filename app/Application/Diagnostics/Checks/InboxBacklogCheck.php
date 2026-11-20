<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Checks;

use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use App\Domain\Inbox\Enums\InboxStatus;
use App\Domain\Inbox\Models\InboxMessage;
use Throwable;

/**
 * Проверка готовности: операционный backlog transactional inbox ({@see InboxMessage}).
 *
 * Считает сообщения в статусах:
 * - {@see InboxStatus::Received} — ещё не обработаны / ждут dispatch;
 * - {@see InboxStatus::Failed} — ошибка обработки, ожидают retry;
 * - {@see InboxStatus::Dead} — исчерпаны попытки ({@see config('inbox.max_attempts')}).
 *
 * `warning`, если есть dead или failed, либо received > 500 (задержка приёма/обработки).
 * Не переводит сводный readiness в HTTP 503 (только отдельная проверка → `failed` при исключении БД).
 *
 * Вызывается из {@see DiagnosticsService::runReadinessChecks()} (`name`: `inbox_backlog`).
 */
final class InboxBacklogCheck implements IDiagnosticCheck
{
    /**
     * @return DiagnosticCheckResult status: ok | warning | failed
     */
    public function run(): DiagnosticCheckResult
    {
        try {
            $received = InboxMessage::query()->where('status', InboxStatus::Received)->count();
            $failed   = InboxMessage::query()->where('status', InboxStatus::Failed)->count();
            $dead     = InboxMessage::query()->where('status', InboxStatus::Dead)->count();

            $status = $dead > 0 || $failed > 0 || $received > 500 ? 'warning' : 'ok';

            return new DiagnosticCheckResult(
                name: 'inbox_backlog',
                status: $status,
                message: 'Проверен backlog inbox.',
                context: [
                    'received' => $received,
                    'failed'   => $failed,
                    'dead'     => $dead,
                ],
            );
        } catch (Throwable $exception) {
            return new DiagnosticCheckResult(
                name: 'inbox_backlog',
                status: 'failed',
                message: $exception->getMessage(),
            );
        }
    }
}
