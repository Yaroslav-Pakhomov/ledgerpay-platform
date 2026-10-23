<?php

declare(strict_types=1);

namespace App\Http\Resources\AccountBalanceSnapshot\Backoffice;

use App\Domain\Ledger\Snapshots\Models\AccountBalanceSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountBalanceSnapshot
 */
class AccountBalanceSnapshotResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'uuid'            => $this->uuid,
            'account_uuid'    => $this->account?->uuid,
            'customer_email'  => $this->account?->customer?->email,
            'balance'         => $this->balance,
            'currency'        => $this->currency,
            'ledger_entry_id' => $this->ledger_entry_id,
            'snapshotted_at'  => $this->snapshotted_at->toDateTimeString(),
            'metadata'        => $this->metadata,
        ];
    }
}
