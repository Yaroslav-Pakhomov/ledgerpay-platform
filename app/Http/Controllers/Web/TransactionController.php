<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Audit\Services\AuditLogger;
use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\DTO\CreateTransferData;
use App\Application\Transaction\DTO\CreateWithdrawalData;
use App\Application\Transaction\Results\TransactionCreationResult;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\Web\DepositRequest;
use App\Http\Requests\Transaction\Web\TransferRequest;
use App\Http\Requests\Transaction\Web\WithdrawRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * WEB-контроллер для работы с транзакциями.
 *
 * Принимает HTTP-запросы, передает данные в TransactionService
 * и возвращает сообщение об успехе.
 *
 * Здесь нет бизнес-логики — только связь между WEB и сервисом.
 *
 * Практический эффект для клиента:
 * • deposit (пополнение) только на свой счёт;
 * • withdraw (снятие) только со своего счёта;
 * • transfer (перевод) только между своими счетами (нужен view на оба).
 *
 * Для back office:
 * • view проходит на любой счёт,
 * • create тоже — может операции по любым счетам.
 *
 * Audit: {@see AuditAction::TransactionQueued} — только при `$result->created`
 * (новая pending-тx и dispatch job); {@see AuditAction::TransactionRetried} —
 * только если транзакция была в статусе Failed до retry.
 */
final class TransactionController extends Controller
{
    /**
     * @param AuditLogger $audit Сервис записи audit-событий
     */
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Создаёт операцию пополнения счёта.
     *
     * Данные из запроса упаковываются в DTO и передаются в TransactionService.
     * Транзакция создаётся в статусе Pending и ставится в очередь на обработку.
     *
     * @param  DepositRequest     $request            Валидированные данные (target_account_uuid, amount, currency)
     * @param  TransactionService $transactionService Сервис создания и постановки транзакции в очередь
     * @return RedirectResponse   Redirect back с flash-сообщением об успехе
     *
     * @throws ModelNotFoundException если целевой счёт не найден
     * @throws AuthorizationException при отсутствии права view на счёт или create на Transaction
     * @throws Throwable              при ошибке транзакции БД в TransactionService
     */
    public function deposit(DepositRequest $request, TransactionService $transactionService): RedirectResponse
    {
        $validated = $request->validated();

        $targetAccount = Account::query()->where('uuid', $validated['target_account_uuid'])->firstOrFail();

        // Право работать с этим конкретным счетом
        $this->authorize('view', $targetAccount);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $result = $transactionService->deposit(
            new CreateDepositData(
                $validated['target_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
            )
        );

        $this->auditQueuedIfCreated($result, $request);

        return back()->with('success', 'Транзакция по депозиту поставлена в очередь.');
    }

    /**
     * Создаёт операцию списания со счёта.
     *
     * Данные из запроса упаковываются в DTO и передаются в TransactionService.
     * Транзакция создаётся в статусе Pending и ставится в очередь на обработку.
     *
     * @param  WithdrawRequest    $request            Валидированные данные (source_account_uuid, amount, currency)
     * @param  TransactionService $transactionService Сервис создания и постановки транзакции в очередь
     * @return RedirectResponse   Redirect back с flash-сообщением об успехе
     *
     * @throws ModelNotFoundException если счёт-источник не найден
     * @throws AuthorizationException при отсутствии права view на счёт или create на Transaction
     * @throws Throwable              при ошибке транзакции БД в TransactionService
     */
    public function withdraw(WithdrawRequest $request, TransactionService $transactionService): RedirectResponse
    {
        $validated = $request->validated();

        $sourceAccount = Account::query()->where('uuid', $validated['source_account_uuid'])->firstOrFail();

        // Право работать с этим конкретным счетом
        $this->authorize('view', $sourceAccount);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $result = $transactionService->withdraw(
            new CreateWithdrawalData(
                $validated['source_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
            )
        );

        $this->auditQueuedIfCreated($result, $request);

        return back()->with('success', 'Транзакция вывода поставлена в очередь.');
    }

    /**
     * Создаёт операцию перевода между счетами.
     *
     * Данные из запроса упаковываются в DTO и передаются в TransactionService.
     * Транзакция создаётся в статусе Pending и ставится в очередь на обработку.
     *
     * @param  TransferRequest    $request            Валидированные данные (source_account_uuid, target_account_uuid,
     *                                                amount, currency)
     * @param  TransactionService $transactionService Сервис создания и постановки транзакции в очередь
     * @return RedirectResponse   Redirect back с flash-сообщением об успехе
     *
     * @throws ModelNotFoundException если один из счетов не найден
     * @throws AuthorizationException при отсутствии права view на счета или create на Transaction
     * @throws Throwable              при ошибке транзакции БД в TransactionService
     */
    public function transfer(TransferRequest $request, TransactionService $transactionService): RedirectResponse
    {
        $validated = $request->validated();

        $accountSource = Account::query()->where('uuid', $validated['source_account_uuid'])->firstOrFail();
        $accountTarget = Account::query()->where('uuid', $validated['target_account_uuid'])->firstOrFail();

        // Право работать с этими конкретными счетами
        $this->authorize('view', $accountSource);
        $this->authorize('view', $accountTarget);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $result = $transactionService->transfer(
            new CreateTransferData(
                $validated['source_account_uuid'],
                $validated['target_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
            )
        );

        $this->auditQueuedIfCreated($result, $request);

        return back()->with('success', 'Транзакция перевода поставлена в очередь.');
    }

    /**
     * Повторная попытка неудачной транзакции.
     *
     * Требует права retry (эквивалент view на транзакцию).
     * Если транзакция не в статусе Failed, сервис вернёт её без повторной постановки в очередь.
     *
     * @param  string             $uuid               UUID транзакции для повтора
     * @param  TransactionService $transactionService Сервис повторной постановки транзакции в очередь
     * @return RedirectResponse   Redirect back с flash-сообщением об успехе
     *
     * @throws ModelNotFoundException если транзакция не найдена
     * @throws AuthorizationException при отсутствии права retry
     */
    public function retry(string $uuid, TransactionService $transactionService): RedirectResponse
    {
        $transaction = Transaction::query()
            ->with(['sourceAccount', 'targetAccount'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // retry = «можешь ли ты видеть эту транзакцию»
        $this->authorize('retry', $transaction);

        $retried = $transactionService->retry($uuid);

        if ($transaction->status === TransactionStatus::Failed) {
            $this->audit->log(
                auditAction: AuditAction::TransactionRetried,
                entity: $retried,
                request: request(),
            );
        }

        return back()->with('success', 'Транзакция повторной попытки поставлена в очередь.');
    }

    /**
     * Пишет {@see AuditAction::TransactionQueued} только при фактическом создании
     * pending-транзакции (не при idempotent replay).
     */
    private function auditQueuedIfCreated(
        TransactionCreationResult $result,
        Request $request,
    ): void {
        if (!$result->created) {
            return;
        }

        $transaction = $result->transaction;

        $this->audit->log(
            auditAction: AuditAction::TransactionQueued,
            entity: $transaction,
            metadata: [
                'type'     => $transaction->type->value,
                'amount'   => $transaction->amount,
                'currency' => $transaction->currency,
            ],
            request: $request,
        );
    }
}
