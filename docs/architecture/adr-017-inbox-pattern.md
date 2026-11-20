# ADR-017: Транзакционный inbox (входящие события)

## Статус

Принято.

## Контекст

Внешние системы могут доставлять события повторно (повторные вызовы webhook, повторная доставка). Без устойчивого приёма в базу данных и идемпотентной обработки возможны повторные побочные эффекты.

LedgerPay уже использует транзакционный **outbox** на исходящей границе ([ADR-006](./adr-006-outbox-pattern.md)). Нужна симметричная **входящая** граница.

[ADR-015](./adr-015-prometheus-metrics.md) — метрики необработанного inbox; [ADR-016](./adr-016-opentelemetry-tracing.md) — `traceparent` в заголовках inbox и сегмент `ledgerpay.inbox.process`.

## Решение

1. **Приём** — вставка в `inbox_messages` с ограничением UNIQUE `(source, external_message_id)` и `payload_hash` (SHA-256 канонического JSON). Повтор с тем же содержимым — та же строка; другое содержимое — конфликт (HTTP 409).
2. **Без немедленной постановки в Redis** при приёме — только PostgreSQL.
3. **Постановка в очередь** — команда `inbox:dispatch-pending`: `FOR UPDATE SKIP LOCKED`, поле `dispatch_lease_until`, задание `ProcessInboxMessageJob` в очередь `inbox` (`tries=1` в Horizon).
4. **Обработка** — `lockForUpdate`, обработчик и статус `processed` в одной транзакции; при ошибках — `failed` / `dead`, отложенный повтор через поле `available_at` в БД.
5. **Демонстрационный обработчик** — событие `external.ping` → таблица `inbox_ping_receipts` (идемпотентный эффект в PostgreSQL).
6. **Webhook** — Bearer `INBOX_DEMO_TOKEN`; в промышленной эксплуатации — проверка подписи провайдера.
7. **Внешние HTTP- и Kafka-вызовы** внутри транзакции обработчика не выполняем; исходящий результат — через outbox в той же транзакции PostgreSQL.

## Гарантии

- Событие сохраняется после фиксации транзакции в БД.
- Дедупликация приёма по паре «источник + внешний идентификатор».
- Восстановление после сбоя постановки в очередь (истёкшая аренда dispatch).
- Последовательная обработка одной записи за счёт блокировки строки.

## Не гарантируется

- Сквозная доставка «ровно один раз» до внешних API.
- Метрика `ledgerpay_inbox_processing` как число активных воркеров (статус `processing` кратковременен внутри транзакции).

## Последствия

- Рост таблицы `inbox_messages`, нужна политика хранения.
- Неизвестные типы событий сейчас повторяются до статуса `dead` (в промышленной эксплуатации — различать постоянные и временные ошибки).

## Демонстрационный webhook

```bash
export INBOX_DEMO_TOKEN='<из .env>'

curl -i \
  -X POST http://localhost:8080/api/v1/integrations/inbox/events \
  -H "Authorization: Bearer $INBOX_DEMO_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "external_message_id": "demo-event-001",
    "event_name": "external.ping",
    "payload": { "ping": true }
  }'
```

Ожидание: **HTTP 202**, `status: received`.

## Связанные решения

- ADR-006 (outbox), ADR-015, ADR-016.
