<?php

declare(strict_types=1);

namespace App\Domain\Outbox\Models;

use App\Application\Outbox\Jobs\PublishOutboxMessageJob;
use App\Application\Outbox\Services\OutboxPublisher;
use App\Application\Outbox\Services\OutboxWriter;
use App\Console\Commands\DispatchPendingOutboxMessagesCommand;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Запись transactional outbox для надёжной публикации доменных событий.
 *
 * Создаётся через {@see OutboxWriter}
 * в той же DB-транзакции, что и бизнес-изменение (например {@see Transaction}).
 *
 * Публикация выполняется асинхронно:
 * {@see DispatchPendingOutboxMessagesCommand}
 * → {@see PublishOutboxMessageJob}
 * → {@see OutboxPublisher}.
 *
 * @property int                       $id
 * @property string                    $uuid           Публичный идентификатор outbox-записи
 * @property string                    $event_name     Имя события, например transaction.created
 * @property string                    $aggregate_type FQCN агрегата
 * @property int                       $aggregate_id   PK агрегата
 * @property string|null               $aggregate_uuid Публичный UUID агрегата
 * @property array<string, mixed>      $payload        Тело доменного события
 * @property array<string, mixed>|null $headers        Метаданные (request_id, occurred_at)
 * @property OutboxStatus              $status
 * @property int                       $attempts       Число попыток публикации
 * @property Carbon|null               $available_at   Когда сообщение снова доступно для dispatch
 * @property Carbon|null               $published_at   Время успешной публикации
 * @property string|null               $last_error     Текст последней ошибки
 * @property Carbon                    $created_at
 * @property Carbon                    $updated_at
 */
#[Fillable([
    'uuid',
    'event_name',
    'aggregate_type',
    'aggregate_id',
    'aggregate_uuid',
    'payload',
    'headers',
    'status',
    'attempts',
    'available_at',
    'published_at',
    'last_error',
])]
final class OutboxMessage extends Model
{
    use HasUuids;

    #[Override]
    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'headers'      => 'array',
            'status'       => OutboxStatus::class,
            'attempts'     => 'integer',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Поля, для которых Laravel должен генерировать UUID.
     *
     * @return array<int, string>
     */
    #[Override]
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Сообщение ожидает dispatch/publish.
     */
    public function isPending(): bool
    {
        return $this->status === OutboxStatus::Pending;
    }
}
