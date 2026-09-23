<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/**
 * Контракт типизированного доменного события.
 *
 * Реализация описывает имя события, идентичность агрегата, тело события (payload)
 * и дополнительные метаданные (headers).
 * Запись в transactional outbox выполняется в прикладном слое (см. ADR-009).
 */
interface IDomainEvent
{
    public function eventName(): string;

    public function aggregateType(): string;

    public function aggregateId(): int;

    public function aggregateUuid(): ?string;

    /** @return array<string, mixed> */
    public function payload(): array;

    /** @return array<string, mixed> */
    public function headers(): array;
}
