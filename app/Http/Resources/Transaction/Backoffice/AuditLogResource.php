<?php

declare(strict_types=1);

namespace App\Http\Resources\Transaction\Backoffice;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON-представление {@see AuditLog} для backoffice UI.
 *
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     actor: string|null,
     *     action: string,
     *     entity_type: string|null,
     *     entity_uuid: string|null,
     *     metadata: array<string, mixed>|null,
     *     request_id: string|null,
     *     ip_address: string|null,
     *     created_at: string,
     * }
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'actor'       => $this->actor?->email,
            'action'      => $this->action->value,
            'entity_type' => $this->entity_type,
            'entity_uuid' => $this->entity_uuid,
            'metadata'    => $this->metadata,
            'request_id'  => $this->request_id,
            'ip_address'  => $this->ip_address,
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}
