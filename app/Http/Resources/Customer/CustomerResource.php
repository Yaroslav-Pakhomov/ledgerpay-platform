<?php

declare(strict_types=1);

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed      $uuid
 * @property mixed      $name
 * @property mixed      $email
 * @property mixed      $status
 * @property mixed|null $created_at
 */
class CustomerResource extends JsonResource
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
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'email'      => $this->email,
            'status'     => $this->status?->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
