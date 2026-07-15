<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\DTO\CreateTransferData;
use App\Application\Transaction\DTO\CreateWithdrawalData;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\DepositRequest;
use App\Http\Requests\Transaction\TransferRequest;
use App\Http\Requests\Transaction\WithdrawRequest;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

/**
 * API-контроллер для работы с транзакциями.
 *
 * Принимает HTTP-запросы, передает данные в TransactionService
 * и возвращает результат в формате JSON.
 *
 * Здесь нет бизнес-логики — только связь между API и сервисом.
 */
final class TransactionController extends Controller
{
    /**
     * Возвращает список транзакций с пагинацией.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transaction::class);

        $transactions = Transaction::query()
            ->with(['sourceAccount', 'targetAccount'])
            ->latest()
            ->paginate(20);

        return TransactionResource::collection($transactions);
    }

    /**
     * Создает операцию пополнения счета.
     *
     * Данные из запроса упаковываются в DTO
     * и передаются в TransactionService.
     *
     * @throws Throwable
     */
    public function deposit(
        DepositRequest $request,
        TransactionService $service,
    ): TransactionResource {
        $targetAccount = Account::query()->where('uuid', $request->string('target_account_uuid'))->firstOrFail();

        $this->authorize('view', $targetAccount);

        $transaction = $service->deposit(
            new CreateDepositData(
                targetAccountUuid: $request->string('target_account_uuid')->toString(),
                amount: $request->integer('amount'),
                currency: $request->string('currency')->toString(),
                idempotencyKey: $request->idempotencyKey(),
            )
        );

        return new TransactionResource(
            $transaction->load(['sourceAccount', 'targetAccount'])
        );
    }

    /**
     * Создает операцию списания со счета.
     *
     * Данные из запроса упаковываются в DTO
     * и передаются в TransactionService.
     *
     * @throws Throwable
     */
    public function withdraw(
        WithdrawRequest $request,
        TransactionService $service,
    ): TransactionResource {
        $sourceAccount = Account::query()->where('uuid', $request->string('source_account_uuid'))->firstOrFail();

        $this->authorize('view', $sourceAccount);

        $transaction = $service->withdraw(
            new CreateWithdrawalData(
                sourceAccountUuid: $request->string('source_account_uuid')->toString(),
                amount: $request->integer('amount'),
                currency: $request->string('currency')->toString(),
                idempotencyKey: $request->idempotencyKey(),
            )
        );

        return new TransactionResource(
            $transaction->load(['sourceAccount', 'targetAccount'])
        );
    }

    /**
     * Создает операцию перевода между счетами.
     *
     * Данные из запроса упаковываются в DTO
     * и передаются в TransactionService.
     *
     * @throws Throwable
     */
    public function transfer(
        TransferRequest $request,
        TransactionService $service,
    ): TransactionResource {
        $sourceAccount = Account::query()->where('uuid', $request->string('source_account_uuid'))->firstOrFail();
        $targetAccount = Account::query()->where('uuid', $request->string('target_account_uuid'))->firstOrFail();

        $this->authorize('view', $sourceAccount);
        $this->authorize('view', $targetAccount);

        $transaction = $service->transfer(
            new CreateTransferData(
                sourceAccountUuid: $request->string('source_account_uuid')->toString(),
                targetAccountUuid: $request->string('target_account_uuid')->toString(),
                amount: $request->integer('amount'),
                currency: $request->string('currency')->toString(),
                idempotencyKey: $request->idempotencyKey(),
            )
        );

        return new TransactionResource(
            $transaction->load(['sourceAccount', 'targetAccount'])
        );
    }

    /**
     * Повторно ставит failed-транзакцию в очередь.
     */
    public function retry(
        string $uuid,
        TransactionService $service,
    ): TransactionResource {
        $transaction = Transaction::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('retry', $transaction);

        $transaction = $service->retry($uuid);

        return new TransactionResource(
            $transaction->load(['sourceAccount', 'targetAccount'])
        );
    }

    /**
     * Возвращает одну транзакцию по uuid.
     *
     * Вместе с транзакцией загружаются связанные счета
     * и записи в бухгалтерской книге.
     *
     * TransactionResource
     */
    public function show(string $uuid): array
    {
        $transaction = Transaction::query()
            ->with(['sourceAccount', 'targetAccount', 'ledgerEntries'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->authorize('view', $transaction);

        return TransactionResource::make($transaction)->resolve();
    }
}
