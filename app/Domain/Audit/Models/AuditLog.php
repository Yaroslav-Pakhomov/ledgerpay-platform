<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuditAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable запись журнала аудита (append-only).
 *
 * Каждая строка фиксирует: кто ({@see actor()}), что ({@see AuditAction}),
 * над какой сущностью и с каким HTTP/request context.
 *
 * Инвариант: update/delete запрещены в {@see booted()} → {@see LogicException}.
 *
 * @property int $id
 * @property int|null $actor_user_id
 * @property AuditAction $action
 * @property string|null $entity_type FQCN модели, например App\Domain\Transaction\Models\Transaction
 * @property int|null $entity_id
 * @property string|null $entity_uuid Публичный UUID сущности, если есть
 * @property array<string, mixed>|null $metadata
 * @property string|null $request_id Correlation ID (X-Request-Id)
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $actor
 */
final class AuditLog extends Model
{
    /**
     * Таблица, связанная с моделью.
     */
    protected $table = 'audit_logs';

    protected $fillable = [
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'entity_uuid',
        'metadata',
        'request_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'action'   => AuditAction::class,
        'metadata' => 'array',
    ];

    /**
     * Запрещает изменение и удаление записей после создания.
     *
     * Логирование хранит историю операций — уже созданную запись
     * нельзя править или удалять, только добавлять новые.
     */
    protected static function booted(): void
    {
        // Любая попытка изменить запись завершится ошибкой.
        self::updating(function (): never {
            throw new LogicException('Записи в логировании являются неизменяемыми.');
        });

        // Любая попытка удалить запись завершится ошибкой.
        self::deleting(function (): never {
            throw new LogicException('Записи в логировании являются неизменяемыми.');
        });
    }

    /**
     * Пользователь, инициировавший действие.
     *
     * `null` для системных/фоновых событий (queue worker без HTTP-сессии).
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'id');
    }
}
