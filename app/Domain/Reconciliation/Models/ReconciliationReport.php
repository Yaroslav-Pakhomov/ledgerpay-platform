<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation\Models;

use App\Application\Reconciliation\Services\ReconciliationService;
use App\Domain\Account\Models\Account;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Override;

/**
 * Отчёт сверки баланса счёта vs балансом реестра записей.
 *
 * Фиксация только на добавление: каждый запуск
 *  {@see ReconciliationService}
 *  создаёт новую запись для расследований в бэк-офисе.
 *
 * @property int                       $id
 * @property string                    $uuid
 * @property int                       $account_id
 * @property int                       $account_balance
 * @property int                       $ledger_balance
 * @property int                       $difference
 * @property string                    $currency
 * @property ReconciliationStatus      $status
 * @property Carbon                    $checked_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon                    $created_at
 * @property Carbon                    $updated_at
 * @property-read Account|null         $account
 */
#[Fillable([
    'uuid',
    'account_id',
    'account_balance',
    'ledger_balance',
    'difference',
    'currency',
    'status',
    'checked_at',
    'metadata',
])]
final class ReconciliationReport extends Model
{
    use HasUuids;

    /**
     * Запрещает изменение и удаление записей после создания.
     *
     * Отчёты сверки хранят историю операций — уже созданную запись
     * нельзя править или удалять, только добавлять новые.
     */
    #[Override]
    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException('Отчёты сверки являются неизменяемыми.');
        });

        self::deleting(function (): never {
            throw new LogicException('Отчёты сверки являются неизменяемыми.');
        });
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'account_balance' => 'integer',
            'ledger_balance'  => 'integer',
            'difference'      => 'integer',
            'status'          => ReconciliationStatus::class,
            'checked_at'      => 'datetime',
            'metadata'        => 'array',
        ];
    }

    /**
     * Поля, для которых Laravel генерирует UUID.
     *
     * @return array<int, string>
     */
    #[Override]
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Счёт, для которого выполнена сверка.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * Баланс счёта совпадает с балансом реестра записей.
     */
    public function isMatched(): bool
    {
        return $this->status === ReconciliationStatus::Matched;
    }
}
