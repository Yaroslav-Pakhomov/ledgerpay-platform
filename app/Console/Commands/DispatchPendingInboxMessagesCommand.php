<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Inbox\Jobs\ProcessInboxMessageJob;
use App\Domain\Inbox\Enums\InboxStatus;
use App\Domain\Inbox\Models\InboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Scheduler-команда: dispatch восстанавливаемых inbox-сообщений в очередь {@see config('inbox.queue')}.
 *
 * Выбирает {@see InboxMessage} со status {@see InboxStatus::Received} или {@see InboxStatus::Failed},
 * у которых `available_at` <= now() и истёк или отсутствует `dispatch_lease_until`,
 * и ставит {@see ProcessInboxMessageJob} (с `traceparent` из headers сообщения, если есть).
 *
 * В отличие от {@see DispatchPendingOutboxMessagesCommand}:
 * - одна запись за итерацию с `FOR UPDATE SKIP LOCKED` и lease ({@see config('inbox.dispatch_lease_seconds')});
 * - при ошибке dispatch сбрасывает lease, чтобы сообщение можно было забрать снова.
 *
 * Планируется в {@see routes/console.php} каждую минуту: `withoutOverlapping()->onOneServer()`.
 */
#[Signature('inbox:dispatch-pending {--limit= : Maximum number of messages}')]
#[Description('Отправить восстанавливаемые inbox-сообщения в очередь Redis.')]
final class DispatchPendingInboxMessagesCommand extends Command
{
    /**
     * @return int Command::SUCCESS
     *
     * @throws Throwable проброс из {@see claimNextMessage()} при сбое транзакции
     */
    public function handle(): int
    {
        $limit = (int) ($this->option('limit') ?: config('inbox.dispatch_batch_size', 100));
        $limit = max(1, min(1000, $limit));

        $dispatched = 0;

        for ($i = 0; $i < $limit; $i++) {
            $message = $this->claimNextMessage();

            if ($message === null) {
                break;
            }

            try {
                ProcessInboxMessageJob::dispatch(
                    inboxMessageId: $message->id,
                    traceparent: is_string($message->headers['traceparent'] ?? null)
                        ? $message->headers['traceparent']
                        : null,
                )->onQueue(config('inbox.queue', 'inbox'));

                $dispatched++;
            } catch (Throwable $exception) {
                report($exception);

                InboxMessage::query()
                    ->whereKey($message->id)
                    ->where('status', $message->status->value)
                    ->update(['dispatch_lease_until' => null]);

                $this->error('Не удалось поставить inbox-сообщение в очередь.');
            }
        }

        $this->info("Отправлено {$dispatched} inbox-сообщений.");

        return self::SUCCESS;
    }

    /**
     * Атомарно резервирует следующее сообщение и продлевает dispatch-lease.
     *
     * @throws Throwable
     */
    private function claimNextMessage(): ?InboxMessage
    {
        return DB::transaction(function (): ?InboxMessage {
            $message = InboxMessage::query()
                ->whereIn('status', [
                    InboxStatus::Received->value,
                    InboxStatus::Failed->value,
                ])
                ->where('available_at', '<=', now())
                ->where(function ($query): void {
                    $query
                        ->whereNull('dispatch_lease_until')
                        ->orWhere('dispatch_lease_until', '<=', now());
                })
                ->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if ($message === null) {
                return null;
            }

            $leaseSeconds                  = (int) config('inbox.dispatch_lease_seconds', 180);
            $message->dispatch_lease_until = now()->addSeconds($leaseSeconds);
            $message->save();

            return $message;
        });
    }
}
