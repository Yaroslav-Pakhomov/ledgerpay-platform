# ADR-006: Outbox Pattern

## Статус

Принято.

## Контекст

Транзакция может быть зафиксирована в БД, а публикация события во внешний брокер — провалиться.

Это создаёт рассогласование между системой учёта (system of record) и downstream-потребителями.

## Решение

Доменные события сначала сохраняются в таблице `outbox_messages` в той же DB-транзакции, что и бизнес-изменение.

Scheduled command `outbox:dispatch-pending` ставит pending/failed записи в очередь `outbox`.

Queue job публикует сообщение (пока через Log) и помечает его как `published`.

События: `transaction.created`, `transaction.completed`, `transaction.failed`.

Жизненный цикл:
- transaction.created     → Pending заведена (HTTP)
- transaction.completed   → деньги двинулись (worker OK)
- transaction.failed      → терминальный сбой после retry (worker failed)

Повтор вручную: (`Failed → Pending`) при повторном сбое может породить **второй** `transaction.failed` — потребители должны быть идемпотентными (проверять по `outbox_uuid`).

## Последствия

Плюсы:

- надёжная публикация событий;
- нет потери событий после DB commit;
- повторяемые сбои;
- операционная видимость через backoffice.

Минусы:

- дополнительная таблица и workers;
- eventual publication (не мгновенная);
- consumers должны быть idempotent (at-least-once delivery).
