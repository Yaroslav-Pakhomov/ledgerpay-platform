<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Customer\Models\Customer;
use App\Domain\Ledger\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Доменная модель банковского счета.
 *
 * Счет принадлежит клиенту и хранит баланс в minor units,
 * например в копейках/центах, а не в рублях/долларах.
 */
final class Account extends Model
{
    /**
     * Подключает автоматическую генерацию UUID для модели.
     */
    use HasUuids;

    /**
     * Явно указываем таблицу, с которой работает модель.
     */
    protected $table = 'accounts';

    /**
     * Разрешаем массовое заполнение всех полей.
     *
     * В реальном проекте можно заменить на $fillable,
     * если нужно жестко контролировать поля.
     */
    protected $guarded = [];

    /**
     * Приведение типов полей модели.
     */
    protected function casts(): array
    {
        return [
            // Баланс всегда будет integer.
            'balance' => 'integer',

            // Статус будет автоматически преобразован в enum AccountStatus.
            'status' => AccountStatus::class,
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
     * Используем uuid вместо id при route model binding.
     *
     * Например:
     * /accounts/{account}
     *
     * Laravel будет искать счет по колонке uuid.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Проверяет, активен ли счет.
     */
    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /**
     * Связь: счет принадлежит одному клиенту.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Связь: у счета может быть много проводок/записей ledger.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id', 'id');
    }
}
