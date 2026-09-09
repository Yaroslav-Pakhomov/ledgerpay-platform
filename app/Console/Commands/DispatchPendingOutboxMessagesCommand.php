<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Outbox\Jobs\PublishOutboxMessageJob;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduler-команда: dispatch pending/failed outbox-сообщений в очередь `outbox`.
 *
 * Выбирает записи {@see OutboxMessage} со status
 * {@see OutboxStatus::Pending} или {@see OutboxStatus::Failed},
 * у которых `available_at` null или <= now(), и ставит {@see PublishOutboxMessageJob}.
 *
 * Планируется в {@see routes/console.php} каждую минуту с withoutOverlapping().
 */
#[Signature('outbox:dispatch-pending {--limit=100}')]
#[Description('Отправить ожидающие обработки сообщения из папки «Исходящие» в очередь.')]
final class DispatchPendingOutboxMessagesCommand extends Command
{
    /**
     * @return int Command::SUCCESS
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $outboxMessages = OutboxMessage::query()
            ->whereIn('status', [OutboxStatus::Pending, OutboxStatus::Failed])
            ->where(function ($query) {
                $query
                    ->whereNull('available_at')
                    ->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($outboxMessages as $outboxMessage) {
            PublishOutboxMessageJob::dispatch($outboxMessage->id);
        }

        $this->info("Отправленные {$outboxMessages->count()} исходящие сообщения.");

        return self::SUCCESS;
    }
}
