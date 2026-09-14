<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

/**
 * Тип действия в журнале аудита.
 *
 * Строковое значение case сохраняется в колонке `audit_logs.action`.
 * Охватывает события аутентификации, счетов, транзакций и backoffice.
 *
 * Группы действий:
 *
 * Аутентификация:
 * - user_registered — регистрация пользователя;
 * - user_logged_in — успешный вход;
 * - user_logged_out — выход из системы.
 *
 * Счета:
 * - account_created — создан новый счёт.
 *
 * Транзакции:
 * - transaction_created — транзакция создана (зарезервировано, пока не логируется);
 * - transaction_queued — транзакция поставлена в очередь на обработку;
 * - transaction_completed — транзакция успешно завершена;
 * - transaction_failed — транзакция завершилась ошибкой;
 * - transaction_retried — повторная попытка обработки транзакции.
 *
 * Backoffice:
 * - backoffice_customer_viewed — просмотр карточки клиента (зарезервировано);
 * - backoffice_dashboard_viewed — просмотр дашборда (зарезервировано).
 *
 * Используется для:
 * - типобезопасной записи событий через AuditLogger;
 * - фильтрации и отображения журнала аудита в backoffice;
 * - compliance, расследований и отладки операций.
 */
enum AuditAction: string
{
    case UserRegistered = 'user_registered';
    case UserLoggedIn   = 'user_logged_in';
    case UserLoggedOut  = 'user_logged_out';

    case AccountCreated = 'account_created';

    case TransactionCreated   = 'transaction_created';
    case TransactionQueued    = 'transaction_queued';
    case TransactionCompleted = 'transaction_completed';
    case TransactionFailed    = 'transaction_failed';
    case TransactionRetried   = 'transaction_retried';

    case BackofficeCustomerViewed  = 'backoffice_customer_viewed';
    case BackofficeDashboardViewed = 'backoffice_dashboard_viewed';

}
