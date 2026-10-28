<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\ReadModel\Projectors\AccountSummaryProjector;
use App\Application\ReadModel\Projectors\TransactionSummaryProjector;
use App\Domain\Account\Models\Account;
use App\Domain\ReadModel\Models\AccountSummaryReadModel;
use App\Domain\ReadModel\Models\TransactionSummaryReadModel;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Полная пересборка проекций счетов и операций (`read-models:rebuild`).
 *
 * Обходит все строки в `accounts` и `transactions`, вызывает
 * {@see AccountSummaryProjector} и {@see TransactionSummaryProjector}.
 * Используется после миграций, при рассинхроне проекций с основными таблицами
 * и по расписанию (см. `routes/console.php`, `dailyAt('03:00')`).
 *
 * Опции:
 * - `--truncate` — очистить таблицы проекций перед пересборкой;
 * - `--chunk` — размер пакета для {@see Builder::chunkById()} (по умолчанию 500).
 */
#[Signature('read-models:rebuild {--truncate} {--chunk=500}')]
#[Description('Пересобрать проекции счетов и операций.')]
final class RebuildReadModelsCommand extends Command
{
    /**
     * Пересчитывает все проекции пакетами и выводит счётчики в консоль.
     *
     * При `--truncate` очищаются обе таблицы проекций (операции, затем счета).
     *
     * @return int {@see Command::SUCCESS}
     */
    public function handle(
        AccountSummaryProjector $accountProjector,
        TransactionSummaryProjector $transactionProjector,
    ): int {
        $chunk = max(1, (int) $this->option('chunk'));

        if ((bool) $this->option('truncate')) {
            TransactionSummaryReadModel::query()->delete();
            AccountSummaryReadModel::query()->delete();
        }

        $accounts     = 0;
        $transactions = 0;

        Account::query()
            ->with('customer')
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use ($accountProjector, &$accounts): void {
                foreach ($items as $account) {
                    $accountProjector->project($account);
                    $accounts++;
                }
            });

        Transaction::query()
            ->with(['sourceAccount.customer', 'targetAccount.customer'])
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use ($transactionProjector, &$transactions): void {
                foreach ($items as $transaction) {
                    $transactionProjector->project($transaction);
                    $transactions++;
                }
            });

        $this->info("Пересчитано счетов: {$accounts}, операций: {$transactions}.");

        return self::SUCCESS;
    }
}
