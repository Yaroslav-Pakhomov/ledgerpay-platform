<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Models;

use App\Domain\Inbox\Enums\InboxStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int         $id
 * @property string      $uuid
 * @property string      $source
 * @property string      $external_message_id
 * @property string      $event_name
 * @property string      $payload_hash
 * @property array       $payload
 * @property array|null  $headers
 * @property InboxStatus $status
 * @property int         $attempts
 * @property Carbon      $received_at
 * @property Carbon      $available_at
 * @property Carbon|null $dispatch_lease_until
 * @property Carbon|null $processed_at
 * @property Carbon|null $last_failed_at
 * @property string|null $last_error_code
 * @property string|null $last_error
 */
#[Fillable([
    'uuid',
    'source',
    'external_message_id',
    'event_name',
    'payload_hash',
    'payload',
    'headers',
    'status',
    'attempts',
    'received_at',
    'available_at',
    'dispatch_lease_until',
    'processed_at',
    'last_failed_at',
    'last_error_code',
    'last_error',
])]
final class InboxMessage extends Model
{
    use HasUuids;

    #[\Override]
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    #[\Override]
    protected static function booted(): void
    {
        self::creating(function (self $message): void {
            if ($message->getAttribute('uuid') === null) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'payload'              => 'array',
            'headers'              => 'array',
            'status'               => InboxStatus::class,
            'attempts'             => 'integer',
            'received_at'          => 'immutable_datetime',
            'available_at'         => 'immutable_datetime',
            'dispatch_lease_until' => 'immutable_datetime',
            'processed_at'         => 'immutable_datetime',
            'last_failed_at'       => 'immutable_datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            InboxStatus::Processed,
            InboxStatus::Ignored,
            InboxStatus::Dead,
        ], true);
    }
}
