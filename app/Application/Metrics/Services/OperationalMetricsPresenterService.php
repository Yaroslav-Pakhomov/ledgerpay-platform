<?php

declare(strict_types=1);

namespace App\Application\Metrics\Services;

use App\Application\Metrics\MetricsSnapshot;
use App\Http\Controllers\Web\Backoffice\OperationalMetricsController;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Данные для Inertia-страницы операционных метрик в бэк-офисе.
 *
 * Тот же {@see MetricsSnapshot}, что и GET /metrics, без токена и без опроса эндпоинта.
 * {@see OperationalMetricsController}.
 */
final readonly class OperationalMetricsPresenterService
{
    public function __construct(
        private MetricsSnapshot $snapshot,
    ) {}

    /**
     * @return array{
     *     sections: list<array{title: string, items: list<array{key: string, label: string, value: int|float|null}>}>,
     *     meta: array{
     *         snapshot_timestamp_seconds: int|null,
     *         snapshot_age_seconds: int|null,
     *         is_stale: bool,
     *         is_empty: bool,
     *         max_snapshot_age_seconds: int,
     *         metrics_endpoint: string,
     *     }
     * }
     *
     * is_stale — пустой снимок или возраст больше ledgerpay_metrics.max_snapshot_age_seconds
     *
     * @throws InvalidArgumentException
     */
    public function present(): array
    {
        $values    = $this->snapshot->values();
        $timestamp = isset($values['snapshot_timestamp_seconds']) ? (int) $values['snapshot_timestamp_seconds'] : null;

        $ageSeconds = $timestamp !== null && $timestamp > 0 ? max(0, (int) now()->timestamp - $timestamp) : null;

        $maxAge = (int) config('ledgerpay_metrics.max_snapshot_age_seconds', 180);

        return [
            'sections' => $this->sections($values),
            'meta'     => [
                'snapshot_timestamp_seconds' => $timestamp,
                'snapshot_age_seconds'       => $ageSeconds,
                'is_stale'                   => $values === [] || $ageSeconds === null || $ageSeconds > $maxAge,
                'is_empty'                   => $values === [],
                'max_snapshot_age_seconds'   => $maxAge,
                'metrics_endpoint'           => url('/metrics'),
            ],
        ];
    }

    /**
     * Группировка метрик для интерфейса (ключи = имена без префикса ledgerpay_).
     *
     * @param  array<string, int|float>                                                                          $values
     * @return list<array{title: string, items: list<array{key: string, label: string, value: int|float|null}>}>
     */
    private function sections(array $values): array
    {
        $get = static fn (string $key): int|float|null => $values[$key] ?? null;

        return [
            [
                'title' => 'Снимок и сбор',
                'items' => [
                    ['key' => 'snapshot_timestamp_seconds', 'label' => 'Время снимка (Unix)', 'value' => $get('snapshot_timestamp_seconds')],
                    ['key' => 'collector_success', 'label' => 'Сбор по БД успешен (1/0)', 'value' => $get('collector_success')],
                    ['key' => 'queue_collector_success', 'label' => 'Сбор очередей Redis успешен (1/0)', 'value' => $get('queue_collector_success')],
                ],
            ],
            [
                'title' => 'Операции',
                'items' => [
                    ['key' => 'transactions_pending', 'label' => 'В ожидании', 'value' => $get('transactions_pending')],
                    ['key' => 'transactions_processing', 'label' => 'В обработке', 'value' => $get('transactions_processing')],
                    ['key' => 'transactions_completed', 'label' => 'Завершены', 'value' => $get('transactions_completed')],
                    ['key' => 'transactions_failed', 'label' => 'Ошибка', 'value' => $get('transactions_failed')],
                    ['key' => 'transactions_cancelled', 'label' => 'Отменены', 'value' => $get('transactions_cancelled')],
                ],
            ],
            [
                'title' => 'Outbox',
                'items' => [
                    ['key' => 'outbox_pending', 'label' => 'В ожидании', 'value' => $get('outbox_pending')],
                    ['key' => 'outbox_processing', 'label' => 'В обработке', 'value' => $get('outbox_processing')],
                    ['key' => 'outbox_published', 'label' => 'Опубликовано', 'value' => $get('outbox_published')],
                    ['key' => 'outbox_failed', 'label' => 'Ошибка', 'value' => $get('outbox_failed')],
                    ['key' => 'outbox_oldest_pending_age_seconds', 'label' => 'Возраст старейшего в ожидании (сек)', 'value' => $get('outbox_oldest_pending_age_seconds')],
                ],
            ],
            [
                'title' => 'Очереди (готовые задания)',
                'items' => [
                    ['key' => 'queue_transactions_total_backlog', 'label' => 'Сегменты операций (сумма)', 'value' => $get('queue_transactions_total_backlog')],
                    ['key' => 'queue_outbox_backlog', 'label' => 'Outbox', 'value' => $get('queue_outbox_backlog')],
                    ['key' => 'queue_default_backlog', 'label' => 'Default', 'value' => $get('queue_default_backlog')],
                ],
            ],
            [
                'title' => 'Сверка и проекции',
                'items' => [
                    ['key' => 'reconciliation_last_check_age_seconds', 'label' => 'Секунд с последней сверки (-1 = не было)', 'value' => $get('reconciliation_last_check_age_seconds')],
                    ['key' => 'projection_account_row_gap', 'label' => 'Разрыв по счетам (число строк)', 'value' => $get('projection_account_row_gap')],
                    ['key' => 'projection_transaction_row_gap', 'label' => 'Разрыв по операциям (число строк)', 'value' => $get('projection_transaction_row_gap')],
                ],
            ],
        ];
    }
}
