<?php

declare(strict_types=1);

namespace App\Http\Resources\Transaction\Api;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'uuid'                => $this->uuid,
            'type'                => $this->type->value,
            'status'              => $this->status->value,
            'source_account_uuid' => $this->whenLoaded(
                'sourceAccount',
                fn () => $this->sourceAccount?->uuid
            ),
            'target_account_uuid' => $this->whenLoaded(
                'targetAccount',
                fn () => $this->targetAccount?->uuid
            ),
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'failure_reason' => $this->failure_reason,
            'processed_at'   => $this->processed_at?->toISOString(),
            'created_at'     => $this->created_at->toISOString(),
        ];
    }
}
