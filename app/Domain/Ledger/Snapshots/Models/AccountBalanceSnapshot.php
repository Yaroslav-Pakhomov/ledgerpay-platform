<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Snapshots\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Override;

/**
 * Неизменяемый снимок баланса счёта на момент времени.
 *
 * Используется для поэтапной сверки: базовый баланс снимка
 * плюс delta по проводкам после `ledger_entry_id`.
 *
 * @property int $balance Баланс в minor units
 * @property-read Account|null $account
 * @property-read LedgerEntry|null $ledgerEntry
 */
#[Fillable([
    'uuid',
    'account_id',
    'ledger_entry_id',
    'balance',
    'currency',
    'snapshotted_at',
    'metadata',
])]
final class AccountBalanceSnapshot extends Model
{
    use HasUuids;

    #[Override]
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Снимки баланса являются неизменяемыми.');
        });

        self::deleting(function (): never {
            throw new LogicException('Снимки баланса являются неизменяемыми.');
        });
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'balance'        => 'integer',
            'snapshotted_at' => 'datetime',
            'metadata'       => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * @return BelongsTo<LedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'ledger_entry_id', 'id');
    }
}
