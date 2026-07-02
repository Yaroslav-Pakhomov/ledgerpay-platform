<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Domain\Account\Enums\AccountStatus;
use App\Domain\Account\Exceptions\CurrencyMismatchException;
use App\Domain\Account\Exceptions\InactiveAccountException;
use App\Domain\Account\Exceptions\InsufficientFundsException;
use App\Domain\Customer\Models\Customer;
use App\Domain\Ledger\Models\LedgerEntry;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Доменная модель банковского счета.
 *
 * Счет принадлежит клиенту и хранит баланс в minor units,
 * например в копейках/центах, а не в рублях/долларах.
 *
 * @property AccountStatus $status
 */
final class Account extends Model
{
    /**
     * Подключает test factory для доменного агрегата Account.
     */
    use HasFactory;

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
     * Связывает доменную модель с test factory.
     *
     * Модели в App\Domain\...\Models не резолвят factory автоматически,
     * поэтому явно указываем infrastructure-слой (Database\Factories).
     */
    protected static function newFactory(): AccountFactory
    {
        return AccountFactory::new();
    }

    /**
     * Проверяет, активен ли счет.
     */
    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /**
     * Зачисляет средства на счет.
     *
     * Проверяет активность счета и совпадение валют,
     * затем увеличивает баланс.
     */
    public function credit(int $amount, string $currency): void
    {
        $this->assertActive();
        $this->assertCurrency($currency);
        $this->balance += $amount;
    }

    /**
     * Списывает средства со счета.
     *
     * Проверяет активность, валюту и достаточность средств,
     * затем уменьшает баланс.
     */
    public function debit(int $amount, string $currency): void
    {
        $this->assertActive();
        $this->assertCurrency($currency);
        $this->assertSufficientFunds($amount);
        $this->balance -= $amount;
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

    /**
     * Проверяет доменный инвариант:
     * денежные операции разрешены только для активных счетов.
     */
    private function assertActive(): void
    {
        if (!$this->isActive()) {
            throw new InactiveAccountException;
        }
    }

    /**
     * Проверяет доменный инвариант:
     * валюта операции должна совпадать с валютой счета.
     */
    private function assertCurrency(string $currency): void
    {
        if ($this->currency !== strtoupper($currency)) {
            throw new CurrencyMismatchException;
        }
    }

    /**
     * Проверяет доменный инвариант:
     * списание невозможно, если средств недостаточно.
     */
    private function assertSufficientFunds(int $amount): void
    {
        if ($this->balance < $amount) {
            throw new InsufficientFundsException;
        }
    }
}
