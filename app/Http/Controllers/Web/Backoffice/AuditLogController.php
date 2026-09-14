<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\Transaction\Backoffice\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Response;

/**
 * Backoffice: просмотр immutable журнала аудита.
 *
 * GET `/backoffice/audit-logs` — paginated список с фильтрами
 * `action` и `request_id`. Доступ только через middleware `backoffice`.
 */
final class AuditLogController extends Controller
{
    /**
     * Страница журнала аудита с фильтрами и пагинацией.
     *
     * Query-параметры:
     * - `action` — значение {@see AuditAction} (например `transaction_completed`);
     * - `request_id` — correlation ID запроса.
     *
     * Фильтры сохраняются при переходе по страницам ({@see LengthAwarePaginator::withQueryString()}).
     *
     * @param  Request  $request Query: action, request_id, page
     * @return Response Inertia-страница Backoffice/AuditLogs
     */
    public function index(Request $request): Response
    {
        $action    = $request->string('action')->toString();
        $requestId = $request->string('request_id')->toString();

        $logs = AuditLog::query()
            ->with('actor')
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($requestId !== '', fn ($query) => $query->where('request_id', $requestId))
            ->latest()
            ->paginate(50)
            ->withQueryString() // с учётом фильтров/запроса
            ->through(fn (AuditLog $log) => AuditLogResource::make($log)->resolve());

        return inertia('Backoffice/AuditLogs', [
            'filters' => [
                'action'     => $action,
                'request_id' => $requestId,
            ],
            'logs' => $logs,
        ]);
    }
}
