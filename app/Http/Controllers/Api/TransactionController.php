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
use App\Http\Requests\Transaction\Api\DepositRequest;
use App\Http\Requests\Transaction\Api\TransferRequest;
use App\Http\Requests\Transaction\Api\WithdrawRequest;
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
 *
 * Практический эффект для клиента:
 *  • deposit (пополнение) только на свой счёт;
 *  • withdraw (снятие) только со своего счёта;
 *  • transfer (перевод) только между своими счетами (нужен view на оба).
 *
 *  Для back office:
 *  • view проходит на любой счёт,
 *  • create тоже — может операции по любым счетам.
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
        $validated = $request->validated();

        $targetAccount = Account::query()->where('uuid', $request->string('target_account_uuid'))->firstOrFail();

        // Право работать с этим конкретным счетом
        $this->authorize('view', $targetAccount);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $transaction = $service->deposit(
            new CreateDepositData(
                $validated['target_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
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
        $validated = $request->validated();

        $sourceAccount = Account::query()->where('uuid', $request->string('source_account_uuid'))->firstOrFail();

        // Право работать с этим конкретным счетом
        $this->authorize('view', $sourceAccount);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $transaction = $service->withdraw(
            new CreateWithdrawalData(
                $validated['source_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
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
        $validated = $request->validated();

        $sourceAccount = Account::query()->where('uuid', $request->string('source_account_uuid'))->firstOrFail();
        $targetAccount = Account::query()->where('uuid', $request->string('target_account_uuid'))->firstOrFail();

        // Право работать с этими конкретными счетами
        $this->authorize('view', $sourceAccount);
        $this->authorize('view', $targetAccount);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $transaction = $service->transfer(
            new CreateTransferData(
                $validated['source_account_uuid'],
                $validated['target_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
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

        // retry = «можешь ли ты видеть эту транзакцию»
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
