<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transaction\Backoffice\TransactionResource;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Контроллер списка транзакций в бэк-офисе.
 *
 * Формирует список транзакций с пагинацией
 * и поддерживает фильтрацию по статусу и типу.
 */
final class TransactionController extends Controller
{
    /**
     * Отображает список транзакций.
     */
    public function index(Request $request): Response
    {
        // Получаем значения фильтров из query-параметров.
        // Если параметр отсутствует, будет возвращена пустая строка.
        $status = $request->string('status')->toString();
        $type   = $request->string('type')->toString();

        // Формируем выборку транзакций.
        $transactionsPaginator = Transaction::query()
            // Загружаем исходный и целевой счета вместе с их владельцами,
            // чтобы избежать проблемы N+1 запросов.
            ->with(['sourceAccount.customer', 'targetAccount.customer'])
            // Применяем фильтр по статусу,
            // только если значение было передано в запросе.
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            // Применяем фильтр по типу транзакции,
            // только если значение было передано в запросе.
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->latest('id')
            ->cursorPaginate(50)
            // Сохраняет status/type при переходе на страницу 2
            ->withQueryString();

        // Преобразуем модели внутри пагинатора в массивы,
        // содержащие только необходимые интерфейсу данные.
        $transactions         = $transactionsPaginator->toArray();
        $transactions['data'] = TransactionResource::collection(
            $transactionsPaginator->items()
        )->resolve();

        return inertia('Backoffice/Transactions', [
            'filters' => [
                'status' => $status,
                'type'   => $type,
            ],
            'transactions' => $transactions,
        ]);
    }
}
