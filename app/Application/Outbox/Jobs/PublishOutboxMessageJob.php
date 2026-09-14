<?php

declare(strict_types=1);

namespace App\Application\Outbox\Jobs;

use App\Application\Outbox\Services\OutboxPublisher;
use App\Console\Commands\DispatchPendingOutboxMessagesCommand;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Infrastructure job для асинхронной публикации outbox-сообщения.
 *
 * Связывает scheduler ({@see DispatchPendingOutboxMessagesCommand})
 * с transport-слоем ({@see OutboxPublisher}). Job не содержит доменной логики —
 * только lock, смену статусов и делегирование publish.
 *
 * Идемпотентность:
 * - записи в status {@see OutboxStatus::Published} и {@see OutboxStatus::Processing} пропускаются;
 * - {@see WithoutOverlapping} снижает параллельную обработку одного message id.
 *
 * При исчерпании retry {@see failed()} переводит запись в {@see OutboxStatus::Failed}
 * с отложенным `available_at` для повторного dispatch.
 */
#[Backoff(15)]
#[Tries(5)]
final class PublishOutboxMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param int $outboxMessageId PK записи {@see OutboxMessage}
     */
    public function __construct(public readonly int $outboxMessageId)
    {
        $this->onQueue('outbox');
    }

    /**
     * WithoutOverlapping защищает от параллельной публикации одного outbox message.
     *
     * - releaseAfter(15) — отложить повтор при overlap;
     * - expireAfter(120) — снять lock, если worker упал без release.
     *
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('outbox:' . $this->outboxMessageId)
                ->releaseAfter(15)
                ->expireAfter(120),
        ];
    }

    /**
     * Блокирует запись, публикует через {@see OutboxPublisher}, помечает published.
     *
     * Выполняется в DB-транзакции: status processing → publish → published.
     *
     * @throws Throwable
     */
    public function handle(OutboxPublisher $outboxPublisher): void
    {
        DB::transaction(function () use ($outboxPublisher) {

            $outboxMessage = OutboxMessage::query()
                ->whereKey($this->outboxMessageId)
                ->lockForUpdate()
                ->first();

            if (!$outboxMessage instanceof OutboxMessage) {
                throw new ModelNotFoundException('Исходящее сообщение не найдено.');
            }

            if ($outboxMessage->status === OutboxStatus::Published) {
                return;
            }

            if ($outboxMessage->status === OutboxStatus::Processing) {
                return;
            }

            $outboxMessage->update([
                'status'     => OutboxStatus::Processing,
                'attempts'   => $outboxMessage->attempts + 1,
                'last_error' => null,
            ]);

            $outboxPublisher->publish($outboxMessage);

            $outboxMessage->update([
                'status'       => OutboxStatus::Published,
                'published_at' => now(),
            ]);
        });
    }

    /**
     * Фиксирует терминальный статус {@see OutboxStatus::Failed} после исчерпания retry.
     *
     * `available_at` сдвигается в будущее, чтобы {@see DispatchPendingOutboxMessagesCommand}
     * мог повторно dispatch'ить сообщение.
     */
    public function failed(Throwable $exception): void
    {
        $message = OutboxMessage::query()->find($this->outboxMessageId);

        if (!$message instanceof OutboxMessage) {
            return;
        }
        $message->update([
            'status'       => OutboxStatus::Failed,
            'last_error'   => $exception->getMessage(),
            'available_at' => now()->addMinutes(),
        ]);
    }
}
