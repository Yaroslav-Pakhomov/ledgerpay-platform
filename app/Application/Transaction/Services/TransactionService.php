<?php

declare(strict_types=1);

namespace App\Application\Transaction\Services;

use App\Application\Outbox\Services\OutboxWriterService;
use App\Application\ReadModel\Services\ReadModelProjectionService;
use App\Application\Tracing\Services\TracingService;
use App\Application\Tracing\Support\SpanNames;
use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\DTO\CreateTransferData;
use App\Application\Transaction\DTO\CreateWithdrawalData;
use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Application\Transaction\Results\TransactionCreationResult;
use App\Application\Transaction\Routing\TransactionQueueRouter;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Events\TransactionCreated;
use App\Domain\Transaction\Events\TransactionRetried;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Support\Facades\DB;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;

/**
 * Application Service: создание транзакций и постановка в очередь.
 *
 * Вызывается из HTTP/API: агрегат в статусе Pending, outbox {@see TransactionCreated}
 * через {@see OutboxWriterService::recordEvent()} (в той же DB-транзакции),
 * затем dispatch {@see ProcessTransactionJob}.
 *
 * Ручной retry: Failed → Pending, outbox {@see TransactionRetried}, снова job.
 *
 * Движение денег — {@see TransactionProcessorService} в worker.
 *
 * Трассировка (в активном HTTP-сегменте), через {@see TracingService::withinSpan}:
 * {@see SpanNames::TRANSACTION_IDEMPOTENCY} → {@see SpanNames::TRANSACTION_PERSIST}
 * → {@see SpanNames::TRANSACTION_ENQUEUE}; далее consumer {@see SpanNames::TRANSACTION_PROCESS}
 * в {@see ProcessTransactionJob}. Атрибуты: `transaction.type`, `transaction.id`;
 * `idempotency.hit` — только при быстром совпадении в {@see SpanNames::TRANSACTION_IDEMPOTENCY}
 * (без вложенного PERSIST). При гонке дубликат может обнаружиться уже в
 * {@see SpanNames::TRANSACTION_PERSIST} (`lockForUpdate`): без INSERT, без ENQUEUE,
 * атрибут `idempotency.hit` не выставляется. Ключ идемпотентности в spans не пишется.
 * Путь {@see self::retry()} enqueue без `TRANSACTION_ENQUEUE` (наследует только HTTP).
 */
final readonly class TransactionService
{
    public function __construct(
        private OutboxWriterService $outboxWriter,
        private TransactionQueueRouter $queueRouter,
        private ReadModelProjectionService $projectionService,
        private TracingService $tracing,
    ) {}

    /**
     * Создает операцию пополнения счета.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     *
     * @throws Throwable
     */
    public function deposit(CreateDepositData $data): TransactionCreationResult
    {
        /**
         * Создаем транзакцию в состоянии Pending.
         * Фактическое изменение баланса будет выполнено
         * TransactionProcessorService.
         */
        $callbackTransaction = function () use ($data): Transaction {
            /**
             * Получаем агрегат счета по его публичному идентификатору.
             */
            $target = Account::query()->where('uuid', $data->targetAccountUuid)->firstOrFail();

            return Transaction::query()->create([
                'type'                   => TransactionType::Deposit,
                'status'                 => TransactionStatus::Pending,
                'source_account_id'      => null,
                'target_account_id'      => $target->id,
                'amount'                 => $data->amount,
                'currency'               => strtoupper($data->currency),
                'idempotency_key'        => $data->idempotencyKey,
                'idempotency_expires_at' => now()->addHours((int) config('ledgerpay.idempotency.ttl_hours')),
            ]);
        };

        $result = $this->createTransactionOnce($data->idempotencyKey, TransactionType::Deposit, $callbackTransaction);

        $this->dispatchIfNewPending($result, TransactionType::Deposit);

        return new TransactionCreationResult(transaction: $result->transaction->refresh(), created: $result->created);
    }

    /**
     * Создает операцию списания денежных средств.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     *
     * @throws Throwable
     */
    public function withdraw(CreateWithdrawalData $data): TransactionCreationResult
    {
        /**
         * Создаем транзакцию в состоянии Pending.
         * Проверка достаточности средств выполняется позже
         * в TransactionProcessorService при обработке job.
         */
        $callbackTransaction = function () use ($data): Transaction {
            /**
             * Получаем агрегат счета-источника по публичному UUID.
             */
            $source = Account::query()->where('uuid', $data->sourceAccountUuid)->firstOrFail();

            return Transaction::query()->create([
                'type'                   => TransactionType::Withdrawal,
                'status'                 => TransactionStatus::Pending,
                'source_account_id'      => $source->id,
                'target_account_id'      => null,
                'amount'                 => $data->amount,
                'currency'               => strtoupper($data->currency),
                'idempotency_key'        => $data->idempotencyKey,
                'idempotency_expires_at' => now()->addHours((int) config('ledgerpay.idempotency.ttl_hours')),
            ]);
        };

        $result = $this->createTransactionOnce($data->idempotencyKey, TransactionType::Withdrawal, $callbackTransaction);

        $this->dispatchIfNewPending($result, TransactionType::Withdrawal);

        return new TransactionCreationResult(
            transaction: $result->transaction->refresh(),
            created: $result->created,
        );
    }

    /**
     * Создает операцию перевода между счетами.
     *
     * Если транзакция с указанным Idempotency-Key уже существует,
     * возвращается ранее созданный результат без повторной обработки.
     *
     * @throws Throwable
     */
    public function transfer(CreateTransferData $data): TransactionCreationResult
    {
        /**
         * Создаем транзакцию в состоянии Pending.
         * Доменные инварианты перевода (активность счетов, валюта,
         * запрет same-account transfer) проверяются в TransactionProcessorService.
         */
        $callbackTransaction = function () use ($data): Transaction {
            /**
             * Получаем агрегаты обоих счетов,
             * участвующих в переводе.
             */
            $source = Account::query()->where('uuid', $data->sourceAccountUuid)->firstOrFail();

            $target = Account::query()->where('uuid', $data->targetAccountUuid)->firstOrFail();

            return Transaction::query()->create([
                'type'                   => TransactionType::Transfer,
                'status'                 => TransactionStatus::Pending,
                'source_account_id'      => $source->id,
                'target_account_id'      => $target->id,
                'amount'                 => $data->amount,
                'currency'               => strtoupper($data->currency),
                'idempotency_key'        => $data->idempotencyKey,
                'idempotency_expires_at' => now()->addHours((int) config('ledgerpay.idempotency.ttl_hours')),
            ]);
        };

        $result = $this->createTransactionOnce($data->idempotencyKey, TransactionType::Transfer, $callbackTransaction);

        $this->dispatchIfNewPending($result, TransactionType::Transfer);

        return new TransactionCreationResult(
            transaction: $result->transaction->refresh(),
            created: $result->created,
        );
    }

    /**
     * Повторно ставит failed-транзакцию в очередь на обработку.
     *
     * Application use case для ручного retry:
     * - переводит агрегат из Failed обратно в Pending;
     * - записывает outbox {@see TransactionRetried} в той же DB-транзакции;
     * - dispatch'ит {@see ProcessTransactionJob}.
     *
     * Повторная обработка допустима только для failed-транзакций.
     *
     * @throws Throwable
     */
    public function retry(string $transactionUuid): Transaction
    {
        $transaction = Transaction::query()->where('uuid', $transactionUuid)->firstOrFail();

        if ($transaction->status !== TransactionStatus::Failed) {
            return $transaction;
        }

        DB::transaction(function () use ($transaction): void {
            /**
             * Сбрасываем статус агрегата перед повторной постановкой в очередь.
             * failure_reason очищается, чтобы worker мог обработать транзакцию заново.
             */
            $transaction->update([
                'status'         => TransactionStatus::Pending,
                'failure_reason' => null,
            ]);

            $this->outboxWriter->recordEvent(
                new TransactionRetried($transaction->refresh()),
            );
        });

        $this->projectionService->projectTransactionAndAccounts($transaction->refresh());

        ProcessTransactionJob::dispatch($transaction->id)
            ->onQueue($this->queueRouter->queueFor($transaction));

        return $transaction->refresh();
    }

    /**
     * Конкурентная идемпотентность и персистенция агрегата.
     *
     * Внешний сегмент {@see SpanNames::TRANSACTION_IDEMPOTENCY}: lookup без блокировки;
     * при раннем hit — `idempotency.hit`, без вложенного PERSIST и без ENQUEUE.
     * Иначе вложенный {@see SpanNames::TRANSACTION_PERSIST}: DB-транзакция Laravel,
     * `lockForUpdate` по `idempotency_key`; при уже существующей записи — `created === false`
     * (без INSERT, без outbox, `idempotency.hit` не выставляется); иначе INSERT и outbox
     * {@see TransactionCreated}. ENQUEUE — только у вызывающего кода при `created === true`.
     * Unique index на `idempotency_key` — финальная защита от гонки.
     *
     * @param callable(): Transaction $callbackTransaction
     *
     * @throws Throwable
     */
    private function createTransactionOnce(string $idempotencyKey, TransactionType $type, callable $callbackTransaction): TransactionCreationResult
    {
        return $this->tracing->withinSpan(
            name: SpanNames::TRANSACTION_IDEMPOTENCY,
            callback: function (SpanInterface $span) use ($idempotencyKey, $type, $callbackTransaction): TransactionCreationResult {
                $existing = $this->findByIdempotencyKey($idempotencyKey);

                /**
                 * Идемпотентность гарантирует,
                 * что повторный запрос не приведет
                 * к повторному движению денежных средств.
                 */
                if ($existing instanceof Transaction) {
                    $span->setAttribute('idempotency.hit', true);

                    return new TransactionCreationResult(
                        transaction: $existing,
                        created: false,
                    );

                }

                return $this->tracing->withinSpan(
                    name: SpanNames::TRANSACTION_PERSIST,
                    callback: fn (): TransactionCreationResult => DB::transaction(function () use ($idempotencyKey, $callbackTransaction): TransactionCreationResult {
                        /**
                         * Пессимистическая блокировка под конкурентные idempotency-запросы.
                         * Вместе с unique index на idempotency_key гарантирует,
                         * что будет создан ровно один агрегат Transaction.
                         */
                        $existing = Transaction::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();

                        if ($existing instanceof Transaction) {
                            return new TransactionCreationResult(
                                transaction: $existing,
                                created: false,
                            );
                        }

                        $transaction = $callbackTransaction();

                        /**
                         * Outbox: {@see TransactionCreated} — в той же DB-транзакции, что и INSERT transaction.
                         * Гарантирует, что внешние потребители узнают о создании операции после commit.
                         */
                        $this->outboxWriter->recordEvent(
                            new TransactionCreated($transaction),
                        );

                        return new TransactionCreationResult(
                            transaction: $transaction,
                            created: true,
                        );
                    }),
                    kind: SpanKind::KIND_INTERNAL,
                    attributes: [
                        'transaction.type' => $type->value,
                    ]);
            },
            kind: SpanKind::KIND_INTERNAL,
            attributes: [
                'transaction.type' => $type->value,
            ]);
    }

    /**
     * Постановка в очередь только что созданной pending-транзакции.
     *
     * При idempotency-replay (`$result->created === false`) job не dispatch'ится —
     * иначе одна запись могла бы обрабатываться многократно, пока status === Pending.
     *
     * Сегмент {@see SpanNames::TRANSACTION_ENQUEUE}: проекция read model
     * ({@see ReadModelProjectionService::projectTransactionAndAccounts()})
     * и dispatch {@see ProcessTransactionJob} в shard-очередь ({@see TransactionQueueRouter}).
     *
     * @throws Throwable
     */
    private function dispatchIfNewPending(TransactionCreationResult $result, TransactionType $type): void
    {
        if (!$result->created || $result->transaction->status !== TransactionStatus::Pending) {
            return;
        }

        $this->tracing->withinSpan(
            name: SpanNames::TRANSACTION_ENQUEUE,
            callback: function () use ($result): void {
                $this->projectionService->projectTransactionAndAccounts($result->transaction);

                ProcessTransactionJob::dispatch($result->transaction->id)
                    ->onQueue($this->queueRouter->queueFor($result->transaction));
            },
            kind: SpanKind::KIND_INTERNAL,
            attributes: [
                'transaction.type' => $type->value,
                'transaction.id'   => (string) $result->transaction->id,
            ],
        );
    }

    /**
     * Выполняет поиск ранее созданной транзакции
     * по ключу идемпотентности.
     */
    private function findByIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return Transaction::query()->where('idempotency_key', $idempotencyKey)->first();
    }
}
