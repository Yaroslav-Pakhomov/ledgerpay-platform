<?php

declare(strict_types=1);

namespace App\Application\Transaction\Routing;

use App\Application\Diagnostics\Checks\QueueBacklogCheck;
use App\Domain\Transaction\Models\Transaction;

/**
 * Маршрутизатор сегмент-очередей для {@see ProcessTransactionJob}.
 *
 * Распределяет задания по Redis-очередям вида `transactions-account-XX`, чтобы:
 * - один и тот же routing account всегда попадал в один сегмент (стабильный `account_id % shards`);
 * - операции по разным счетам обрабатывались параллельно на разных workers;
 * - внутри одной очереди при одном worker-процессе сохранялся FIFO-порядок job'ов.
 *
 * Используется при dispatch из {@see TransactionService} (`deposit` / `withdraw` / `transfer`, retry).
 * Список всех shard-имён — в {@see self::allTransactionQueues()} (например, diagnostics backlog).
 *
 * Количество shards (сегментов): `config('ledgerpay.queues.transaction_shards')` (env `LEDGERPAY_TRANSACTION_QUEUE_SHARDS`, default 16).
 *
 * @see ProcessTransactionJob
 * @see TransactionService
 */
final class TransactionQueueRouter
{
    /**
     * Возвращает имя Redis-очереди для постановки job обработки транзакции.
     *
     * Формат: `transactions-account-{shard}`, где `{shard}` — двузначный индекс `00` … `shards-1`.
     *
     * @param  Transaction      $transaction Агрегат в статусе Pending (или тот же при retry).
     * @return non-empty-string Имя очереди для {@see ProcessTransactionJob::onQueue()}.
     */
    public function queueFor(Transaction $transaction): string
    {
        $accountId = $this->routingAccountId($transaction);

        $shards = max(1, (int) config('ledgerpay.queues.transaction_shards', 16));

        $shard = $accountId % $shards;

        return sprintf('transactions-account-%02d', $shard);
    }

    /**
     * Определяет account id, по которому вычисляется shard.
     *
     * - **Withdrawal / transfer:** {@see Transaction::$source_account_id} — последовательность исходящих списаний с одного счёта.
     * - **Deposit:** {@see Transaction::$target_account_id} (source отсутствует).
     *
     * Не гарантирует глобальный порядок двух счетов в одном transfer; для extreme high-load
     * возможны account command stream или отдельный lock manager.
     *
     *
     * @return int Положительный идентификатор счёта в БД.
     */
    private function routingAccountId(Transaction $transaction): int
    {
        if ($transaction->source_account_id !== null) {
            return (int) $transaction->source_account_id;
        }

        return (int) $transaction->target_account_id;
    }

    /**
     * Все логические имена transaction сегмент-очередей для текущей конфигурации.
     *
     * Удобно для мониторинга (например, {@see QueueBacklogCheck})
     * и конфигурации Horizon supervisors.
     *
     * @return list<non-empty-string> Например `['transactions-account-00', …, 'transactions-account-15']` при 16 shards.
     */
    public function allTransactionQueues(): array
    {
        $shards = max(1, (int) config('ledgerpay.queues.transaction_shards', 16));

        return array_map(
            fn (int $shard): string => sprintf('transactions-account-%02d', $shard),
            range(0, $shards - 1),
        );
    }
}
