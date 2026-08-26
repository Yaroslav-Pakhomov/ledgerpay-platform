<?php

declare(strict_types=1);

namespace App\Http\Resources\Transaction\Backoffice;

use App\Domain\Transaction\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransactionResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'uuid'     => $this->uuid,
            'type'     => $this->type->value,
            'status'   => $this->status->value,
            'amount'   => $this->amount,
            'currency' => $this->currency,

            'source_account_uuid' => $this->whenLoaded(
                'sourceAccount',
                fn () => $this->sourceAccount?->uuid
            ),
            'target_account_uuid' => $this->whenLoaded(
                'targetAccount',
                fn () => $this->targetAccount?->uuid
            ),

            'source_customer_email' => $this->whenLoaded(
                'sourceAccount',
                fn () => $this->sourceAccount?->customer?->email,
            ),
            'target_customer_email' => $this->whenLoaded(
                'targetAccount',
                fn () => $this->targetAccount?->customer?->email,
            ),

            'failure_reason' => $this->failure_reason,
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }
}
