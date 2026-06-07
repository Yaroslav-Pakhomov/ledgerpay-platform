<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Доменная модель финансовой транзакции.
 *
 * Transaction описывает намерение или факт движения средств:
 * - пополнение счета;
 * - списание со счета;
 * - перевод между счетами.
 *
 * Сама транзакция хранит общую информацию об операции,
 * а фактические движения по счетам фиксируются в ledger_entries.
 */
final class Transaction extends Model
{
    /**
     * Подключает автоматическую генерацию UUID.
     */
    use HasUuids;

    /**
     * Таблица, связанная с моделью.
     */
    protected $table = 'transactions';

    /**
     * Разрешает массовое заполнение всех полей модели.
     */
    protected $guarded = [];

    /**
     * Преобразование атрибутов модели.
     */
    protected function casts(): array
    {
        return [
            // Тип операции: deposit, withdrawal или transfer.
            'type' => TransactionType::class,

            // Статус обработки транзакции.
            'status' => TransactionStatus::class,

            // Сумма операции в minor units: копейки, центы и т.д.
            'amount' => 'integer',

            // Дата и время фактической обработки транзакции.
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Поля, для которых Laravel должен генерировать UUID.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Использовать uuid вместо id при Route Model Binding.
     *
     * Например:
     * GET /transactions/{transaction}
     *
     * Laravel будет искать транзакцию по колонке uuid.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Исходный счет операции.
     *
     * Для withdrawal и transfer это счет,
     * с которого списываются средства.
     *
     * Для deposit может быть null,
     * если деньги поступают извне системы.
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id', 'id');
    }

    /**
     * Целевой счет операции.
     *
     * Для deposit и transfer это счет,
     * на который зачисляются средства.
     *
     * Для withdrawal может быть null,
     * если деньги выводятся из системы.
     */
    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'target_account_id', 'id');
    }

    /**
     * Ledger-записи, созданные в рамках этой транзакции.
     *
     * Одна транзакция может породить одну или несколько записей:
     * - deposit: одна credit-запись;
     * - withdrawal: одна debit-запись;
     * - transfer: debit с sourceAccount и credit на targetAccount.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id', 'id');
    }
}
