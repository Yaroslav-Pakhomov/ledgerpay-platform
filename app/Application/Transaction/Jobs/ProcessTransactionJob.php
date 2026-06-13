<?php

declare(strict_types=1);

namespace App\Application\Transaction\Jobs;

use App\Application\Transaction\Services\TransactionProcessorService;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Обработка доменных ошибок: InsufficientFundsException, InactiveAccountException, CurrencyMismatchException и SameAccountTransferException пробрасываются из TransactionProcessorService → job retry ×5 → failed() записывает failure_reason (например, "Недостаточно средств.").
 */
final class ProcessTransactionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 10;

    public function __construct(
        public readonly int $transactionId,
    ) {
        // Название очереди
        $this->onQueue('transactions');
    }

    /**
     * WithoutOverlapping защищает от параллельной обработки одной и той же transaction.
     *
     * DB-level lockForUpdate остается главным механизмом консистентности баланса,
     * а middleware снижает лишнюю конкуренцию на уровне очереди.
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('transaction:'.$this->transactionId),
        ];
    }

    /**
     * @throws Throwable
     */
    public function handle(TransactionProcessorService $processor): void
    {
        $transaction = Transaction::query()->find($this->transactionId);

        if (!$transaction instanceof Transaction) {
            throw new ModelNotFoundException('Транзакция не найдена.');
        }

        if ($transaction->status === TransactionStatus::Completed) {
            return;
        }

        if ($transaction->status === TransactionStatus::Failed) {
            return;
        }

        $processor->process($transaction);
    }

    public function failed(Throwable $exception): void
    {
        $transaction = Transaction::query()->find($this->transactionId);

        if (!$transaction instanceof Transaction) {
            return;
        }

        $transaction->update([
            'status'         => TransactionStatus::Failed,
            'failure_reason' => $exception->getMessage(),
        ]);
    }
}
