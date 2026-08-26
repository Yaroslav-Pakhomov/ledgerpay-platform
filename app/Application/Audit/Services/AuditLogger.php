<?php

declare(strict_types=1);

namespace App\Application\Audit\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Transaction\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Application-сервис записи событий в immutable audit trail.
 *
 * Единая точка INSERT в {@see AuditLog}: HTTP-контроллеры и queue job'ы
 * вызывают {@see self::log()} вместо прямой работы с моделью.
 *
 * Правила:
 * - `actor_user_id` берётся из {@see Auth::id()}, если не передан явно;
 * - в worker-контексте actor обычно `null` (системное событие);
 * - `entity_uuid` заполняется только для моделей с route key `uuid`
 *   ({@see Account}, {@see Transaction}); для {@see User} — `null`;
 * - `request_id` / IP / User-Agent — из HTTP-запроса, в job — `null`;
 * - {@see AuditAction::BackofficeCustomerViewed}: entity = {@see User},
 *   snapshot клиента передаётся в `metadata`.
 */
final class AuditLogger
{
    /**
     * Создаёт append-only запись в журнале аудита.
     *
     * @param  AuditAction          $auditAction Тип события ({@see AuditAction})
     * @param  Model|null           $entity      Затронутая сущность (User, Account, Transaction и т.д.)
     * @param  array<string, mixed> $metadata    Дополнительный контекст (amount, type, exception и т.д.)
     * @param  Request|null         $request     HTTP-контекст; для async-событий — `null`
     * @param  int|null             $actorUserId ID инициатора; по умолчанию {@see Auth::id()}
     * @return AuditLog             Созданная immutable-запись
     */
    public function log(
        AuditAction $auditAction,
        ?Model $entity = null,
        array $metadata = [],
        ?Request $request = null,
        ?int $actorUserId = null,
    ): AuditLog {
        $actorUserId ??= Auth::id();

        return AuditLog::query()->create([
            'actor_user_id' => $actorUserId,
            'action'        => $auditAction->value,
            'entity_type'   => $entity ? $entity::class : null,
            'entity_id'     => $entity?->getKey(),
            'entity_uuid'   => $entity !== null && $entity->getRouteKeyName() === 'uuid'
                ? (string) $entity->getRouteKey()
                : null,
            'metadata'   => $metadata === [] ? null : $metadata,
            'request_id' => $request?->headers->get('X-Request-Id'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
