<?php

declare(strict_types=1);

namespace App\Http\Resources\Reconciliation\Backoffice;

use App\Domain\Reconciliation\Models\ReconciliationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * JSON-представление {@see ReconciliationReport} для backoffice UI.
 *
 * @mixin ReconciliationReport
 */
final class ReconciliationReportResource extends JsonResource
{
    /**
     * @return array{
     *     uuid: string,
     *     account_uuid: string|null,
     *     customer_email: string|null,
     *     account_balance: int,
     *     ledger_balance: int,
     *     difference: int,
     *     currency: string,
     *     status: string,
     *     checked_at: string,
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'account_uuid' => $this->whenLoaded(
                'account',
                fn () => $this->account?->uuid,
            ),
            'customer_email' => $this->whenLoaded(
                'account',
                fn () => $this->account?->customer?->email,
            ),
            'account_balance' => $this->account_balance,
            'ledger_balance'  => $this->ledger_balance,
            'difference'      => $this->difference,
            'currency'        => $this->currency,
            'status'          => $this->status->value,
            'checked_at'      => $this->checked_at->toDateTimeString(),
        ];
    }
}
