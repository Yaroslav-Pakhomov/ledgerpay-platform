<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Checks;

use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Проверка готовности: глубина очереди заданий.
 *
 * - `database` — COUNT по таблице `jobs`;
 * - `redis` — LLEN для `transactions`, `outbox`, `default` (с префиксом Redis из конфигурации);
 * - иные драйверы — проверка пропускается (статус `ok`).
 *
 * ≥ 1000 заданий суммарно → `warning`.
 */
final class QueueBacklogCheck implements IDiagnosticCheck
{
    public function run(): DiagnosticCheckResult
    {
        try {
            $connection = config('queue.default');

            if ($connection === 'database') {
                $count = DB::table('jobs')->count();

                return new DiagnosticCheckResult(
                    name: 'queue_backlog',
                    status: $count < 1000 ? 'ok' : 'warning',
                    message: 'Проверен backlog очереди в БД.',
                    context: [
                        'connection' => 'database',
                        'jobs'       => $count,
                    ],
                );
            }

            if ($connection === 'redis') {
                $prefix     = (string) config('database.redis.options.prefix');
                $queueNames = ['transactions', 'outbox', 'default'];
                $counts     = [];

                foreach ($queueNames as $name) {
                    $key          = $prefix . 'queues:' . $name;
                    $counts[$key] = Redis::connection()->llen($key);
                }

                $total = array_sum($counts);

                return new DiagnosticCheckResult(
                    name: 'queue_backlog',
                    status: $total < 1000 ? 'ok' : 'warning',
                    message: 'Проверен backlog очереди Redis.',
                    context: [
                        'connection' => 'redis',
                        'queues'     => $counts,
                        'total'      => $total,
                    ],
                );
            }

            return new DiagnosticCheckResult(
                name: 'queue_backlog',
                status: 'ok',
                message: 'Проверка backlog очереди пропущена для текущего подключения.',
                context: [
                    'connection' => $connection,
                ],
            );
        } catch (Throwable $exception) {
            return new DiagnosticCheckResult(
                name: 'queue_backlog',
                status: 'failed',
                message: $exception->getMessage(),
            );
        }
    }
}
