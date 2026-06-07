<?php

declare(strict_types=1);

namespace App\Domain\Customer\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Customer\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Доменная модель клиента.
 *
 * Customer представляет владельца счетов в системе LedgerPay.
 *
 * Для внутренних связей используется числовой id,
 * а UUID выступает публичным идентификатором,
 * который безопасно отдавать во внешнее API.
 */
final class Customer extends Model
{
    /**
     * Подключает автоматическую генерацию UUID.
     */
    use HasUuids;

    /**
     * Таблица, связанная с моделью.
     */
    protected $table = 'customers';

    /**
     * Разрешает массовое заполнение всех атрибутов.
     *
     * При необходимости можно заменить на $fillable
     * для более строгого контроля.
     */
    protected $guarded = [];

    /**
     * Настройка преобразования типов атрибутов.
     */
    protected function casts(): array
    {
        return [
            // Автоматически преобразует значение из БД
            // в enum CustomerStatus и обратно.
            'status' => CustomerStatus::class,
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
     * Использовать uuid для Route Model Binding.
     *
     * Например:
     * GET /customers/{customer}
     *
     * Laravel будет искать клиента по uuid,
     * а не по первичному ключу id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Проверяет, находится ли клиент в активном статусе.
     */
    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    /**
     * Связь "один ко многим".
     *
     * Один клиент может владеть несколькими счетами.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'customer_id', 'id');
    }
}
