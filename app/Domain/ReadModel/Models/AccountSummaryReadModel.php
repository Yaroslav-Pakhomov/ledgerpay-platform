<?php

declare(strict_types=1);

namespace App\Domain\ReadModel\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Override;

#[Fillable([
    'account_id',
    'account_uuid',
    'customer_id',
    'customer_uuid',
    'customer_name',
    'customer_email',
    'currency',
    'balance',
    'status',
    'ledger_entries_count',
    'last_ledger_entry_at',
])]
final class AccountSummaryReadModel extends Model
{
    #[Override]
    protected function casts(): array
    {
        return [
            'balance'              => 'integer',
            'ledger_entries_count' => 'integer',
            'last_ledger_entry_at' => 'datetime',
        ];
    }
}
