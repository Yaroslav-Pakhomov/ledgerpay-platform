# ADR-003: Асинхронная обработка транзакций

## Статус

Принято.

## Контекст

Движение денег не должно быть жёстко связано с задержкой HTTP-запроса.

Обработка включает блокировки, повторные попытки, обработку ошибок и операционный мониторинг.

## Решение

HTTP-слой (`TransactionService`) создаёт транзакцию в статусе **pending**, записывает outbox `transaction.created` (при первом создании, не при idempotent replay — см. [ADR-004](./adr-004-idempotency.md)) и dispatch'ит `ProcessTransactionJob` в очередь Redis **`transactions`**.

Worker (`TransactionProcessorService` внутри job):

- переводит транзакцию в **processing**;
- в DB-транзакции блокирует счета (`lockForUpdate`), двигает баланс, пишет ledger, outbox `transaction.completed` или при ошибке — **failed** и `transaction.failed`.

Ручной **retry** (backoffice / API): только для **failed** → снова **pending**, outbox `transaction.retried`, новый dispatch job; отдельный idempotency key не используется (операция та же).

## Последствия

Плюсы:

- устойчивость;
- поддержка повтора failed-транзакций;
- операционная видимость;
- быстрые HTTP-ответы.

Минусы:

- конечная согласованность (баланс и ledger обновляются после worker);
- worker и scheduler должны быть запущены;
- UI/API должны показывать pending / processing / failed.
