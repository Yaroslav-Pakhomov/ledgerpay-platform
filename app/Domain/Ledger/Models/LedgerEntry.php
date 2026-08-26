<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Запись бухгалтерского журнала (Ledger).
 *
 * Каждое движение средств по счету фиксируется отдельной записью:
 * - списание (Debit);
 * - зачисление (Credit).
 *
 * Ledger является источником финансовой истории системы.
 * Баланс счета может быть пересчитан на основе этих записей.
 *
 * Важное правило:
 * после создания запись не должна изменяться или удаляться.
 * Ошибки исправляются только созданием новых корректирующих записей.
 *
 * @property LedgerDirection $direction
 * @property-read Transaction $transaction
 * @property-read Account     $account
 */
#[Fillable([
    'transaction_id',
    'account_id',
    'direction',
    'amount',
    'currency',
    'balance_after',
])]
#[Table(name: 'ledger_entries')]
final class LedgerEntry extends Model
{
    /**
     * Запрещает изменение и удаление записей после создания.
     *
     * Ledger хранит историю операций — уже созданную запись
     * нельзя править или удалять, только добавлять новые.
     */
    #[\Override]
    protected static function booted(): void
    {
        // Любая попытка изменить запись завершится ошибкой.
        self::updating(function (): never {
            throw new LogicException('Записи в бухгалтерской книге являются неизменяемыми.');
        });

        // Любая попытка удалить запись завершится ошибкой.
        self::deleting(function (): never {
            throw new LogicException('Записи в бухгалтерской книге являются неизменяемыми.');
        });
    }

    /**
     * Преобразование атрибутов модели.
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            // Направление движения средств:
            // Debit или Credit.
            'direction' => LedgerDirection::class,

            // Сумма операции в minor units.
            // Например: копейки или центы.
            'amount' => 'integer',

            // Баланс счета после выполнения операции.
            'balance_after' => 'integer',
        ];
    }

    /**
     * Транзакция, в рамках которой была создана запись.
     *
     * Одна транзакция обычно создает несколько ledger-записей
     * (например, списание с одного счета и зачисление на другой).
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id', 'id');
    }

    /**
     * Счет, к которому относится запись.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * Ledger должен быть неизменяемым (immutable).
     *
     * После создания записи запрещено:
     * - изменять сумму;
     * - менять направление движения;
     * - удалять запись.
     *
     * Дополнительную защиту можно реализовать через:
     * - сервисный слой;
     * - Observer;
     * - Policy;
     * - триггеры БД;
     * - автоматические тесты.
     */
}
