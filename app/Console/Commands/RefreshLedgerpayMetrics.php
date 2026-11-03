<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Metrics\MetricsSnapshot;
use App\Application\Transaction\Routing\TransactionQueueRouter;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Reconciliation\Models\ReconciliationReport;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Команда Artisan: предрасчёт операционных метрик и запись в {@see MetricsSnapshot}.
 *
 * Агрегаты из PostgreSQL (операции, outbox, сверка, разрыв строк проекций)
 * и длины списков Redis-очередей (сегменты `transactions-account-XX`, default, outbox).
 * При ошибке БД снимок не перезаписывается ({@see Command::FAILURE}).
 *
 * Планировщик: {@see routes/console.php} — `ledgerpay:metrics:refresh`, ~1 мин,
 * withoutOverlapping, onOneServer.
 */
#[Description('Обновить предрасчёт операционных метрик для Prometheus')]
#[Signature('ledgerpay:metrics:refresh')]
final class RefreshLedgerpayMetrics extends Command
{
    /**
     * Собирает метрики из БД и Redis, сохраняет снимок; при сбое БД — выход с ошибкой без записи.
     *
     * @return int Command::SUCCESS|Command::FAILURE
     */
    public function handle(MetricsSnapshot $snapshot): int
    {
        // Время снимка и признак успешного сбора по БД (ниже).
        $values = [
            'snapshot_timestamp_seconds' => (int) now()->timestamp,
            'collector_success'          => 1,
        ];

        try {
            // Операции: число записей по каждому статусу.
            $transactions = Transaction::query()->select('status', DB::raw('COUNT(*) AS aggregate'))->groupBy('status')->pluck('aggregate', 'status');

            foreach (['pending', 'processing', 'completed', 'failed', 'cancelled'] as $status) {
                $values['transactions_' . $status] = (int) ($transactions[$status] ?? 0);
            }

            // Исходящие (outbox): счётчики по статусу и возраст старейшего необработанного сообщения.
            $outbox = OutboxMessage::query()->select('status', DB::raw('COUNT(*) AS aggregate'))->groupBy('status')->pluck('aggregate', 'status');

            foreach (['pending', 'processing', 'published', 'failed'] as $status) {
                $values['outbox_' . $status] = (int) ($outbox[$status] ?? 0);
            }

            $created = OutboxMessage::query()->where('status', 'pending')->min('created_at');

            $values['outbox_oldest_pending_age_seconds'] = $created === null ? 0 : max(0, now()->diffInSeconds($created));

            // Сверка: секунд с последнего отчёта; -1 — сверка ещё не выполнялась.
            $checked = ReconciliationReport::query()->max('checked_at');

            $values['reconciliation_last_check_age_seconds'] = $checked === null ? -1 : max(0, now()->diffInSeconds($checked));

            /*
             * Разница числа строк — не истинное отставание проекций.
             * Строки проекций могут быть устаревшими при совпадении количества.
             */
            if (DB::getSchemaBuilder()->hasTable('account_summary_read_models') && DB::getSchemaBuilder()->hasTable('transaction_summary_read_models')) {
                $values['projection_account_row_gap'] = max(0, DB::table('accounts')->count() - DB::table('account_summary_read_models')->count());

                $values['projection_transaction_row_gap'] = max(0, DB::table('transactions')->count() - DB::table('transaction_summary_read_models')->count());
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->error('Сбор метрик из БД не удался: ' . $exception->getMessage());

            // Снимок не перезаписываем — остаётся последний успешный.
            return self::FAILURE;
        }

        try {
            // Очереди Redis: длина списков готовых заданий (сегменты + default + outbox).
            $connection = config('queue.default');

            if ($connection === 'redis') {
                $prefix = (string) config('database.redis.options.prefix');

                $router = app(TransactionQueueRouter::class);

                $transactionTotal = 0;

                foreach ($router->allTransactionQueues() as $queueName) {
                    $transactionTotal += (int) Redis::connection()->llen($prefix . 'queues:' . $queueName);
                }

                $values['queue_transactions_total_backlog'] = $transactionTotal;

                $values['queue_default_backlog'] = (int) Redis::connection()->llen($prefix . 'queues:default');

                $values['queue_outbox_backlog'] = (int) Redis::connection()->llen($prefix . 'queues:outbox');
            }

            $values['queue_collector_success'] = $connection === 'redis' ? 1 : 0;
        } catch (Throwable $exception) {
            report($exception);

            // Метрики БД уже собраны — сохраним снимок с неуспешным сбором очередей.
            $values['queue_collector_success'] = 0;
        }

        $snapshot->save($values);

        $this->info('Снимок метрик обновлён.');

        return self::SUCCESS;
    }
}
