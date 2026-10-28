<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Application\Audit\Services\AuditLoggerService;
use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Customer\Models\Customer;
use App\Domain\ReadModel\Models\TransactionSummaryReadModel;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transaction\Backoffice\TransactionSummaryReadModelResource;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Контроллер главной страницы бэк-офиса.
 *
 * Формирует сводные метрики,
 * список последних клиентов
 * и последние неуспешные транзакции.
 *
 * После загрузки пишет {@see AuditAction::BackofficeDashboardViewed}.
 */
final class DashboardController extends Controller
{
    /**
     * @param AuditLoggerService $audit Сервис записи audit-событий
     */
    public function __construct(
        private readonly AuditLoggerService $audit,
    ) {}

    /**
     * Отображает панель управления бэк-офиса.
     *
     * Audit: {@see AuditAction::BackofficeDashboardViewed} — entity = auth user.
     *
     * @param  Request  $request Query: search (фильтр клиентов)
     * @return Response Inertia-страница Backoffice/Dashboard
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
        $failedTransactions = TransactionSummaryReadModel::query()
            ->where('status', TransactionStatus::Failed->value)
            ->latest('id')
            ->limit(20)
            ->get();

        $this->audit->log(
            auditAction: AuditAction::BackofficeDashboardViewed,
            entity: auth()->user(),
            request: request(),
        );

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
                fn (TransactionSummaryReadModel $transaction): array => TransactionSummaryReadModelResource::make($transaction)->resolve()
            ),
        ]);
    }
}
