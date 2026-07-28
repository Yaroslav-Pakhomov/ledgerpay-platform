<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Transaction\DTO\CreateDepositData;
use App\Application\Transaction\DTO\CreateTransferData;
use App\Application\Transaction\DTO\CreateWithdrawalData;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\Web\DepositRequest;
use App\Http\Requests\Transaction\Web\TransferRequest;
use App\Http\Requests\Transaction\Web\WithdrawRequest;
use Illuminate\Http\RedirectResponse;
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
 */
final class TransactionController extends Controller
{
    /**
     * Создает операцию пополнения счета.
     *
     *  Данные из запроса упаковываются в DTO
     *  и передаются в TransactionService.
     *
     * @throws Throwable
     */
    public function deposit(DepositRequest $request, TransactionService $transactionService): RedirectResponse
    {
        $validated = $request->validated();

        $targetAccount = Account::query()->where('uuid', $validated['target_account_uuid'])->firstOrFail();

        // Право работать с этим конкретным счетом
        $this->authorize('view', $targetAccount);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $transactionService->deposit(
            new CreateDepositData(
                $validated['target_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
            )
        );

        return back()->with('success', 'Транзакция по депозиту поставлена в очередь.');
    }

    /**
     * Создает операцию списания со счета.
     *
     *  Данные из запроса упаковываются в DTO
     *  и передаются в TransactionService.
     *
     * @throws Throwable
     */
    public function withdraw(WithdrawRequest $request, TransactionService $transactionService): RedirectResponse
    {
        $validated = $request->validated();

        $sourceAccount = Account::query()->where('uuid', $validated['source_account_uuid'])->firstOrFail();

        // Право работать с этим конкретным счетом
        $this->authorize('view', $sourceAccount);
        // Право создавать транзакции
        $this->authorize('create', Transaction::class);

        $transactionService->withdraw(
            new CreateWithdrawalData(
                $validated['source_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
            )
        );

        return back()->with('success', 'Транзакция вывода поставлена в очередь');
    }

    /**
     * Создает операцию перевода между счетами.
     *
     *  Данные из запроса упаковываются в DTO
     *  и передаются в TransactionService.
     *
     * @throws Throwable
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

        $transactionService->transfer(
            new CreateTransferData(
                $validated['source_account_uuid'],
                $validated['target_account_uuid'],
                $validated['amount'],
                $validated['currency'],
                $request->idempotencyKey(),
            )
        );

        return back()->with('success', 'Транзакция перевода поставлена в очередь.');
    }

    /**
     * Повтор неудачной транзакции
     */
    public function retry(string $uuid, TransactionService $transactionService): RedirectResponse
    {
        $transaction = Transaction::query()
            ->with(['sourceAccount', 'targetAccount'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // retry = «можешь ли ты видеть эту транзакцию»
        $this->authorize('retry', $transaction);

        $transactionService->retry($uuid);

        return back()->with('success', 'Транзакция повторной попытки поставлена в очередь.');
    }
}
