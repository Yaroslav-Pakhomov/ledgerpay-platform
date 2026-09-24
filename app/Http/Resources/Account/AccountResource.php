<?php

declare(strict_types=1);

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $uuid
 * @property mixed $customer
 * @property mixed $currency
 * @property mixed $balance
 * @property mixed $status
 * @property mixed $created_at
 */
class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'customer_uuid' => $this->whenLoaded(
                'customer',
                fn () => $this->customer->uuid
            ),
            'currency'   => $this->currency,
            'balance'    => $this->balance,
            'status'     => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
        ];

    }
}
