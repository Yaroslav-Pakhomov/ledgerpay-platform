<?php

declare(strict_types=1);

namespace App\Application\Transaction\Services;

use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\DTO\CreateTransferData;
use App\Application\Transaction\DTO\CreateWithdrawalData;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Throwable;

/**
 * Application Service для создания и запуска обработки транзакций.
 *
 * Является точкой входа для сценариев пополнения,
 * списания и перевода денежных средств.
 *
 * Сервис отвечает за:
 * - создание новой транзакции;
 * - применение механизма идемпотентности;
 * - перевод транзакции в начальное состояние Pending;
 * - передачу транзакции в TransactionProcessorService;
 * - обработку ошибок выполнения.
 *
 * Бизнес-логика движения денег не реализуется здесь,
 * а делегируется специализированному TransactionProcessorService.
 */
final readonly class TransactionService
{
    public function __construct(
        private TransactionProcessorService $processor,
    ) {}

    /**
     * Создает операцию пополнения счета.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     */
    public function deposit(CreateDepositData $data): Transaction
    {
        $existing = $this->findByIdempotencyKey($data->idempotencyKey);

        /**
         * Идемпотентность гарантирует,
         * что повторный запрос не приведет
         * к повторному движению денежных средств.
         */
        if ($existing !== null) {
            return $existing;
        }

        /**
         * Получаем агрегат счета по его публичному идентификатору.
         */
        $target = Account::query()
            ->where('uuid', $data->targetAccountUuid)
            ->firstOrFail();

        /**
         * Создаем транзакцию в состоянии Pending.
         * Фактическое изменение баланса будет выполнено
         * TransactionProcessorService.
         */
        $transaction = Transaction::query()->create([
            'type'              => TransactionType::Deposit,
            'status'            => TransactionStatus::Pending,
            'source_account_id' => null,
            'target_account_id' => $target->id,
            'amount'            => $data->amount,
            'currency'          => strtoupper($data->currency),
            'idempotency_key'   => $data->idempotencyKey,
        ]);

        return $this->safeProcess($transaction);
    }

    /**
     * Создает операцию списания денежных средств.
     */
    public function withdraw(CreateWithdrawalData $data): Transaction
    {
        $existing = $this->findByIdempotencyKey($data->idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        $source = Account::query()
            ->where('uuid', $data->sourceAccountUuid)
            ->firstOrFail();

        $transaction = Transaction::query()->create([
            'type'              => TransactionType::Withdrawal,
            'status'            => TransactionStatus::Pending,
            'source_account_id' => $source->id,
            'target_account_id' => null,
            'amount'            => $data->amount,
            'currency'          => strtoupper($data->currency),
            'idempotency_key'   => $data->idempotencyKey,
        ]);

        return $this->safeProcess($transaction);
    }

    /**
     * Создает операцию перевода между счетами.
     */
    public function transfer(CreateTransferData $data): Transaction
    {
        $existing = $this->findByIdempotencyKey($data->idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        /**
         * Получаем агрегаты обоих счетов,
         * участвующих в переводе.
         */
        $source = Account::query()
            ->where('uuid', $data->sourceAccountUuid)
            ->firstOrFail();

        $target = Account::query()
            ->where('uuid', $data->targetAccountUuid)
            ->firstOrFail();

        $transaction = Transaction::query()->create([
            'type'              => TransactionType::Transfer,
            'status'            => TransactionStatus::Pending,
            'source_account_id' => $source->id,
            'target_account_id' => $target->id,
            'amount'            => $data->amount,
            'currency'          => strtoupper($data->currency),
            'idempotency_key'   => $data->idempotencyKey,
        ]);

        return $this->safeProcess($transaction);
    }

    /**
     * Выполняет поиск ранее созданной транзакции
     * по ключу идемпотентности.
     */
    private function findByIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return Transaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Передает транзакцию в обработчик и
     * переводит ее в статус Failed при возникновении ошибки.
     *
     * Такой подход позволяет сохранить историю выполнения
     * и причину неуспешного завершения операции.
     */
    private function safeProcess(Transaction $transaction): Transaction
    {
        try {
            return $this->processor->process($transaction);
        } catch (Throwable $exception) {
            return $this->processor->markAsFailed($transaction, $exception);
        }
    }
}
