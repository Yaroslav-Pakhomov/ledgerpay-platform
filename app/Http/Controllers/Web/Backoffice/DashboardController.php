<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Domain\Account\Models\Account;
use App\Domain\Customer\Models\Customer;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Контроллер главной страницы бэк-офиса.
 *
 * Формирует сводные метрики,
 * список последних клиентов
 * и последние неуспешные транзакции.
 */
final class DashboardController extends Controller
{
    /**
     * Отображает панель управления бэк-офиса.
     */
    public function __invoke(Request $request): Response
    {
        // Получаем поисковую строку из query-параметра `search`.
        // При отсутствии параметра будет возвращена пустая строка.
        $search = $request->string('search')->toString();

        // Формируем выборку последних клиентов.
        $customers = Customer::query()
            // Применяем фильтрацию только при наличии поискового запроса.
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    // Ищем частичное совпадение по имени или email.
                    // По UUID выполняется точный поиск.
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('uuid', $search);
                });
            })
            // Добавляем количество счетов каждого клиента
            // в вычисляемое поле `accounts_count`.
            ->withCount('accounts')
            // Сортируем клиентов от новых к старым.
            ->latest()
            // Ограничиваем объём данных для панели управления.
            ->limit(25)
            ->get();

        // Получаем последние неуспешные транзакции.
        $failedTransactions = Transaction::query()
            // Загружаем связанные счета и их владельцев заранее,
            // чтобы избежать проблемы N+1 запросов.
            ->with([
                'sourceAccount.customer',
                'targetAccount.customer',
            ])
            ->where('status', TransactionStatus::Failed)
            ->latest()
            ->limit(20)
            ->get();

        // Передаём подготовленные данные в Inertia-компонент панели управления.
        return inertia('Backoffice/Dashboard', [
            // Текущие значения фильтров нужны интерфейсу,
            // чтобы сохранить состояние формы поиска.
            'filters' => [
                'search' => $search,
            ],

            // Общие метрики системы.
            'metrics' => [
                'customers'    => Customer::query()->count(),
                'accounts'     => Account::query()->count(),
                'transactions' => Transaction::query()->count(),

                // Количество транзакций, завершившихся ошибкой.
                'failed_transactions' => Transaction::query()
                    ->where('status', TransactionStatus::Failed)
                    ->count(),
            ],

            // Преобразуем модели клиентов в простой массив,
            // содержащий только необходимые интерфейсу поля.
            'customers' => $customers->map(
                fn (Customer $customer): array => [
                    'uuid'           => $customer->uuid,
                    'name'           => $customer->name,
                    'email'          => $customer->email,
                    'status'         => $customer->status->value,
                    'accounts_count' => $customer->accounts_count,
                    'created_at'     => $customer->created_at->toDateTimeString(),
                ]
            ),

            // Подготавливаем данные неуспешных транзакций
            // для отображения в таблице панели управления.
            'failed_transactions' => $failedTransactions->map(
                fn (Transaction $transaction): array => [
                    'uuid'           => $transaction->uuid,
                    'type'           => $transaction->type->value,
                    'status'         => $transaction->status->value,
                    'amount'         => $transaction->amount,
                    'currency'       => $transaction->currency,
                    'failure_reason' => $transaction->failure_reason,

                    // Связанные счёт или клиент могут отсутствовать,
                    // поэтому используется null-safe оператор.
                    'source_customer' => $transaction
                        ->sourceAccount
                        ?->customer
                        ?->email,

                    'target_customer' => $transaction
                        ->targetAccount
                        ?->customer
                        ?->email,

                    'created_at' => $transaction
                        ->created_at
                        ->toDateTimeString(),
                ]
            ),
        ]);
    }
}
