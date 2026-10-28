<?php

declare(strict_types=1);

namespace App\Domain\ReadModel\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Override;

#[Fillable([
    'transaction_id',
    'transaction_uuid',
    'type',
    'status',
    'amount',
    'currency',
    'source_account_uuid',
    'target_account_uuid',
    'source_customer_uuid',
    'source_customer_email',
    'target_customer_uuid',
    'target_customer_email',
    'failure_reason',
    'processed_at',
    'transaction_created_at',
])]
final class TransactionSummaryReadModel extends Model
{
    #[Override]
    protected function casts(): array
    {
        return [
            'amount'                 => 'integer',
            'processed_at'           => 'datetime',
            'transaction_created_at' => 'datetime',
        ];
    }
}
