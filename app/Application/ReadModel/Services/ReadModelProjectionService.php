<?php

declare(strict_types=1);

namespace App\Application\ReadModel\Services;

use App\Application\ReadModel\Projectors\AccountSummaryProjector;
use App\Application\ReadModel\Projectors\TransactionSummaryProjector;
use App\Domain\Transaction\Models\Transaction;

/**
 * Сервис приложения для согласованного обновления проекций после изменений операции.
 *
 * Оркестрирует {@see TransactionSummaryProjector} и {@see AccountSummaryProjector}:
 * сначала проекция операции, затем — счетов-участников (источник и получатель),
 * если они заданы. Вызывается при создании операции в статусе ожидания, после обработки
 * в очереди, при сбое и при ручном повторе.
 *
 * Интерфейс может кратковременно отставать от основных таблиц до завершения вызова.
 */
final readonly class ReadModelProjectionService
{
    public function __construct(
        private AccountSummaryProjector $accountsProjector,
        private TransactionSummaryProjector $transactionsProjector,
    ) {}

    /**
     * Пересчитывает проекцию операции и затронутых счетов.
     *
     * Подгружает {@see Transaction::sourceAccount} и {@see Transaction::targetAccount}
     * при отсутствии в памяти. Баланс и статус счетов в проекции отражают
     * состояние на момент вызова (после успешной обработки — уже с новым балансом).
     *
     * @param Transaction $transaction Операция, для которой нужно обновить проекции
     */
    public function projectTransactionAndAccounts(Transaction $transaction): void
    {
        $transaction->loadMissing(['sourceAccount', 'targetAccount']);

        $this->transactionsProjector->project($transaction);

        if ($transaction->sourceAccount !== null) {
            $this->accountsProjector->project($transaction->sourceAccount);
        }

        if ($transaction->targetAccount !== null) {
            $this->accountsProjector->project($transaction->targetAccount);
        }
    }
}
