<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Application\Reconciliation\Services\ReconciliationService;
use App\Domain\Account\Models\Account;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use App\Domain\Reconciliation\Models\ReconciliationReport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Reconciliation\Backoffice\ReconciliationReportResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Throwable;

/**
 * Бэк-офис: мониторинг сверки балансов.
 *
 * GET `/backoffice/reconciliation` — список с пагинацией {@see ReconciliationReport}
 * с фильтром по status. POST — ручной запуск сверки.
 * Доступ только через middleware `backoffice`.
 */
final class ReconciliationController extends Controller
{
    /**
     * Страница отчётов сверки с фильтром и пагинацией.
     *
     * Query: `status` — matched | mismatched | пусто (все).
     */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $reports = ReconciliationReport::query()
            ->with(['account.customer'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('checked_at')
            ->paginate(50)
            ->withQueryString() // с учётом фильтров/запроса
            ->through(fn (ReconciliationReport $report): array => ReconciliationReportResource::make($report)->resolve());

        return inertia('Backoffice/Reconciliation', [
            'filters' => [
                'status' => $status,
            ],
            'reports' => $reports,
        ]);
    }

    /**
     * Запуск сверки для всех счетов (партию).
     *
     * @throws Throwable
     */
    public function run(ReconciliationService $service): RedirectResponse
    {
        $reports = $service->checkAllAccounts();

        $mismatched = $reports
            ->filter(fn ($report) => $report->status === ReconciliationStatus::Mismatched)
            ->count();

        $message = $mismatched > 0
            ? "Сверка завершена. Расхождений: {$mismatched}."
            : 'Сверка завершена.';

        return back()->with('success', $message);
    }

    /**
     * Сверка одного счёта по UUID.
     *
     * @throws Throwable
     */
    public function runForAccount(string $accountUuid, ReconciliationService $service): RedirectResponse
    {
        $account = Account::query()
            ->where('uuid', $accountUuid)
            ->firstOrFail();

        $report = $service->checkAccount($account);

        $message = $report->status === ReconciliationStatus::Mismatched
            ? 'Сверка счёта завершена. Обнаружено расхождение.'
            : 'Сверка счёта завершена.';

        return back()->with('success', $message);
    }
}
