<?php

declare(strict_types=1);

namespace App\Application\Outbox\Services;

use App\Application\Outbox\Jobs\PublishOutboxMessageJob;
use App\Application\Outbox\Mappers\DomainEventToOutboxMessageMapper;
use App\Application\Transaction\Services\TransactionProcessorService;
use App\Application\Transaction\Services\TransactionService;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Shared\Events\IDomainEvent;
use App\Domain\Transaction\Events\TransactionCreated;

/**
 * Application-сервис записи типизированных доменных событий в transactional outbox.
 *
 * Единственный способ записи: {@see recordEvent()} + {@see IDomainEvent}.
 *
 * Единая точка INSERT в {@see OutboxMessage}. Вызывается из
 * {@see TransactionService} и {@see TransactionProcessorService}
 * **внутри** DB-транзакции — до commit бизнес-изменения.
 *
 * Writer не dispatch'ит jobs и не публикует во внешний broker;
 * это ответственность scheduler + {@see PublishOutboxMessageJob}.
 *
 * @see DomainEventToOutboxMessageMapper
 */
final readonly class OutboxWriter
{
    public function __construct(
        private DomainEventToOutboxMessageMapper $domainEventToOutboxMapper,
    ) {}

    /**
     * Создаёт запись outbox в статусе pending из типизированного доменного события.
     *
     * Транспортные заголовки (`request_id`, `occurred_at`) добавляет {@see DomainEventToOutboxMessageMapper}.
     *
     * @param  IDomainEvent  $event Типизированное доменное событие ({@see TransactionCreated} и др.)
     * @return OutboxMessage Созданная запись со статусом {@see OutboxStatus::Pending}
     */
    public function recordEvent(IDomainEvent $event): OutboxMessage
    {
        return OutboxMessage::query()->create(array_merge(
            $this->domainEventToOutboxMapper->map($event),
            [
                'status'       => OutboxStatus::Pending->value,
                'available_at' => now(),
            ],
        ));
    }
}
