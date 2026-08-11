<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Application\Audit\Services\AuditLogger;
use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Customer\Models\Customer;
use App\Domain\Transaction\Models\Transaction;
use App\Http\Controllers\Controller;
use Inertia\Response;

/**
 * Контроллер страницы клиента в бэк-офисе.
 *
 * Загружает основную информацию о клиенте,
 * принадлежащие ему счета и последние транзакции,
 * связанные с этими счетами.
 *
 * После успешной загрузки пишет {@see AuditAction::BackofficeCustomerViewed}:
 * entity — текущий backoffice-пользователь, snapshot клиента — в metadata.
 */
final class CustomerController extends Controller
{
    /**
     * @param  AuditLogger  $audit  Сервис записи audit-событий
     */
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Отображает подробную информацию о клиенте.
     *
     * Audit: {@see AuditAction::BackofficeCustomerViewed} — `entity` = auth user,
     * `metadata.customer` — snapshot просмотренного клиента, `metadata.accounts` — ID счетов.
     *
     * @param  string  $uuid  UUID клиента
     * @return Response Inertia-страница Backoffice/CustomerShow
     */
    public function show(string $uuid): Response
    {
        // Находим клиента по UUID.
        // Если клиент не существует, Laravel автоматически вернёт ошибку 404.
        $customer = Customer::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Загружаем все счета клиента один раз.
        $accounts = $customer->accounts()->get();

        // Получаем идентификаторы счетов клиента.
        // Они используются для поиска входящих и исходящих транзакций.
        $accountIds = $accounts->pluck('id');

        // Получаем последние транзакции,
        // в которых участвует хотя бы один счёт клиента.
        $transactions = Transaction::query()
            // Загружаем исходный и целевой счета заранее,
            // чтобы избежать дополнительных запросов при обработке коллекции.
            ->with([
                'sourceAccount',
                'targetAccount',
            ])
            ->where(function ($query) use ($accountIds): void {
                $query
                    // Транзакции, отправленные со счетов клиента.
                    ->whereIn('source_account_id', $accountIds)

                    // Транзакции, поступившие на счета клиента.
                    ->orWhereIn('target_account_id', $accountIds);
            })
            // Сортируем транзакции от новых к старым.
            ->latest()
            // Ограничиваем количество записей для страницы клиента.
            ->limit(50)
            ->get();

        $this->audit->log(
            auditAction: AuditAction::BackofficeCustomerViewed,
            entity: auth()->user(),
            metadata: [
                'customer' => [
                    'uuid'       => $customer->uuid,
                    'name'       => $customer->name,
                    'email'      => $customer->email,
                    'status'     => $customer->status->value,
                    'created_at' => $customer->created_at->toDayDateTimeString(),
                ],
                'accounts' => $accountIds->all(),
            ],
            request: request(),
        );

        // Передаём подготовленные данные
        // в Inertia-компонент страницы клиента.
        return inertia('Backoffice/CustomerShow', [
            // Основная информация о клиенте.
            'customer' => [
                'uuid'       => $customer->uuid,
                'name'       => $customer->name,
                'email'      => $customer->email,
                'status'     => $customer->status->value,
                'created_at' => $customer->created_at->toDayDateTimeString(),
            ],

            // Преобразуем счета клиента в массив,
            // содержащий только необходимые интерфейсу поля.
            'accounts' => $accounts->map(
                fn (Account $account): array => [
                    'uuid'       => $account->uuid,
                    'currency'   => $account->currency,
                    'balance'    => $account->balance,
                    'status'     => $account->status->value,
                    'created_at' => $account->created_at->toDayDateTimeString(),
                ]
            ),

            // Подготавливаем связанные с клиентом транзакции
            // для отображения в таблице.
            'transactions' => $transactions->map(
                fn (Transaction $transaction): array => [
                    'uuid'     => $transaction->uuid,
                    'type'     => $transaction->type->value,
                    'status'   => $transaction->status->value,
                    'amount'   => $transaction->amount,
                    'currency' => $transaction->currency,

                    // Связанные счета могут отсутствовать,
                    // поэтому используется null-safe оператор.
                    'source_account_uuid' => $transaction->sourceAccount?->uuid,

                    'target_account_uuid' => $transaction->targetAccount?->uuid,

                    // Причина ошибки будет заполнена
                    // только для неуспешных транзакций.
                    'failure_reason' => $transaction->failure_reason,

                    'created_at' => $transaction->created_at->toDayDateTimeString(),
                ]
            ),
        ]);
    }
}
