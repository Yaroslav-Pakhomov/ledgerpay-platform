<?php

declare(strict_types=1);

namespace App\Application\Inbox\Services;

use App\Domain\Inbox\Models\InboxMessage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Маршрутизация inbox-сообщений по {@see InboxMessage::$event_name} к обработчикам.
 *
 * Вызывается из {@see InboxProcessorService} внутри DB-транзакции со статусом processing.
 * Не меняет статус сообщения — только побочные эффекты по типу события.
 *
 * Демо-обработчик:
 * - `external.ping` — идемпотентная запись в {@see inbox_ping_receipts} (insertOrIgnore).
 *
 * Неизвестный `event_name` → {@see RuntimeException} → {@see InboxProcessorService::recordFailure()}.
 */
final class InboxHandlerRegistryService
{
    /**
     * Выполняет обработчик для event_name сообщения.
     *
     * @throws RuntimeException если тип события не зарегистрирован
     */
    public function handle(InboxMessage $message): void
    {
        match ($message->event_name) {
            'external.ping' => $this->handlePing($message),
            default         => throw new RuntimeException('Неподдерживаемый тип события "Входящие".'),
        };
    }

    /**
     * Демо: фиксирует успешную обработку ping одной строкой на inbox_message_id.
     */
    private function handlePing(InboxMessage $message): void
    {
        DB::table('inbox_ping_receipts')->insertOrIgnore([
            'inbox_message_id' => $message->id,
            'handled_at'       => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }
}
