<?php

declare(strict_types=1);

namespace App\Http\Resources\LedgerEntry;

use App\Domain\Ledger\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerEntry
 */
final class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'transaction_uuid' => $this->whenLoaded(
                'transaction',
                fn () => $this->transaction->uuid
            ),
            'account_uuid' => $this->whenLoaded(
                'account',
                fn () => $this->account->uuid
            ),
            'direction'     => $this->direction->value,
            'amount'        => $this->amount,
            'currency'      => $this->currency,
            'balance_after' => $this->balance_after,
            'created_at'    => $this->created_at->toISOString(),
        ];
    }
}
