<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\Backoffice\IndexAuditLogRequest;
use App\Http\Resources\Transaction\Backoffice\AuditLogResource;
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
     * - `trace_id` — OpenTelemetry trace id (32 hex).
     *
     * Фильтры сохраняются при переходе по страницам ({@see LengthAwarePaginator::withQueryString()}).
     *
     * @param  IndexAuditLogRequest $request Query: action, request_id, page
     * @return Response             Inertia-страница Backoffice/AuditLogs
     */
    public function index(IndexAuditLogRequest $request): Response
    {
        $validated = $request->validated();

        $action    = $validated['action'] ?? '';
        $requestId = $validated['request_id'] ?? '';
        $traceId   = $validated['trace_id'] ?? '';

        $logsPaginator = AuditLog::query()
            ->with('actor')
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($requestId !== '', fn ($query) => $query->where('request_id', $requestId))
            ->when($traceId !== '', fn ($query) => $query->where('trace_id', $traceId))
            ->latest('id')
            ->cursorPaginate(50)
            ->withQueryString(); // с учётом фильтров/запроса

        $logs         = $logsPaginator->toArray();
        $logs['data'] = AuditLogResource::collection($logsPaginator->items())->resolve();

        return inertia('Backoffice/AuditLogs', [
            'filters' => [
                'action'     => $action,
                'request_id' => $requestId,
                'trace_id'   => $traceId,
            ],
            'logs' => $logs,
        ]);
    }
}
