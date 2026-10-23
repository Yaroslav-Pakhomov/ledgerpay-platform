<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Domain\Ledger\Snapshots\Models\AccountBalanceSnapshot;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountBalanceSnapshot\Backoffice\AccountBalanceSnapshotResource;
use Inertia\Response;

/**
 * Бэк-офис: просмотр снимков баланса.
 *
 * GET `/backoffice/balance-snapshots` — cursor pagination {@see AccountBalanceSnapshot}.
 * Доступ только через middleware `backoffice`.
 */
final class BalanceSnapshotController extends Controller
{
    public function index(): Response
    {
        $snapshots = AccountBalanceSnapshot::query()
            ->with('account.customer')
            ->latest('id')
            ->cursorPaginate(50)
            ->through(fn (AccountBalanceSnapshot $snapshot) => AccountBalanceSnapshotResource::make($snapshot)->resolve());

        return inertia('Backoffice/BalanceSnapshots', [
            'snapshots' => $snapshots,
        ]);
    }
}
