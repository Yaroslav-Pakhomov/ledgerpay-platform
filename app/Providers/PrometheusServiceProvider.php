<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Metrics\MetricsSnapshot;
use Illuminate\Support\ServiceProvider;
use Spatie\Prometheus\Collectors\Horizon\CurrentMasterSupervisorCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentProcessesPerQueueCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentWorkloadCollector;
use Spatie\Prometheus\Collectors\Horizon\FailedRecentJobsCollector;
use Spatie\Prometheus\Collectors\Horizon\HorizonStatusCollector;
use Spatie\Prometheus\Collectors\Horizon\JobsPerMinuteCollector;
use Spatie\Prometheus\Collectors\Horizon\RecentJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueDelayedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueOldestPendingJobCollector;
use Spatie\Prometheus\Collectors\Queue\QueuePendingJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueReservedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueSizeCollector;
use Spatie\Prometheus\Facades\Prometheus;

/**
 * Регистрация шкал метрик LedgerPay для spatie/laravel-prometheus.
 *
 * Значения читаются из {@see MetricsSnapshot} (Redis), без SQL при опросе GET /metrics.
 * Префикс имён задаётся в config/prometheus.php (`ledgerpay`).
 *
 * Опционально: {@see registerHorizonCollectors()} и {@see registerQueueCollectors()}
 * — встроенные сборщики Spatie (по умолчанию отключены).
 */
final class PrometheusServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует operational-метрики; значения берутся из снимка в Redis.
     */
    #[\Override]
    public function register(): void
    {
        $gauges = [
            'snapshot_timestamp_seconds'            => 'Unix-время последнего успешного снимка (сек)',
            'collector_success'                     => 'Успешный сбор снимка по БД (1/0)',
            'transactions_pending'                  => 'Операции в ожидании на момент снимка',
            'transactions_processing'               => 'Операции в обработке',
            'transactions_completed'                => 'Завершённые операции',
            'transactions_failed'                   => 'Неуспешные операции',
            'transactions_cancelled'                => 'Отменённые операции',
            'outbox_pending'                        => 'Outbox: в ожидании',
            'outbox_processing'                     => 'Outbox: в обработке',
            'outbox_published'                      => 'Outbox: опубликовано',
            'outbox_failed'                         => 'Outbox: ошибка',
            'outbox_oldest_pending_age_seconds'     => 'Возраст старейшего необработанного outbox (сек)',
            'reconciliation_last_check_age_seconds' => 'Секунд с последней сверки; -1 — сверка не выполнялась',
            'projection_account_row_gap'            => 'Разница числа строк счетов и проекции (не актуальность данных)',
            'projection_transaction_row_gap'        => 'Разница числа строк операций и проекции',
            'queue_transactions_total_backlog'      => 'Сумма готовых заданий по сегмент-очередям операций',
            'queue_default_backlog'                 => 'Готовые задания в default (без отложенных и зарезервированных)',
            'queue_outbox_backlog'                  => 'Готовые задания в outbox',
            'queue_collector_success'               => 'Успешный сбор метрик очередей Redis (1/0)',
        ];

        foreach ($gauges as $name => $help) {
            Prometheus::addGauge($name)->name($name)->helpText($help)->value(static fn (): float => (float) (app(MetricsSnapshot::class)->values()[$name] ?? 0));
        }

        /*
         * Раскомментировать для экспорта метрик Horizon в Prometheus.
         */
        // $this->registerHorizonCollectors();

        /*
         * Раскомментировать для метрик очередей Spatie; передать список имён очередей.
         */
        // $this->registerQueueCollectors(['default']);
    }

    /**
     * Подключает метрики Horizon (супервизоры, нагрузка, задания в минуту и т.д.).
     */
    public function registerHorizonCollectors(): self
    {
        Prometheus::registerCollectorClasses([
            CurrentMasterSupervisorCollector::class,
            CurrentProcessesPerQueueCollector::class,
            CurrentWorkloadCollector::class,
            FailedRecentJobsCollector::class,
            HorizonStatusCollector::class,
            JobsPerMinuteCollector::class,
            RecentJobsCollector::class,
        ]);

        return $this;
    }

    /**
     * Подключает метрики очередей Spatie для указанных имён.
     *
     * @param list<string> $queues     Логические имена Redis-очередей
     * @param string|null  $connection Имя подключения Redis в Laravel
     */
    public function registerQueueCollectors(array $queues = [], ?string $connection = null): self
    {
        Prometheus::registerCollectorClasses([
            QueueSizeCollector::class,
            QueuePendingJobsCollector::class,
            QueueDelayedJobsCollector::class,
            QueueReservedJobsCollector::class,
            QueueOldestPendingJobCollector::class,
        ], compact('connection', 'queues'));

        return $this;
    }
}
