# ADR-004: Ключи идемпотентности для движения средств

## Статус

Принято.

## Контекст

Клиенты могут повторять запросы из-за сетевых сбоев или тайм-аутов.

Без идемпотентности повтор приводит к дублированию вложения/вывода/перевода средств.

## Решение

### REST API

Эндпоинты `POST /api/v1/transactions/deposit`, `/withdraw`, `/transfer` требуют заголовок `Idempotency-Key` (см. OpenAPI).

- Ключ **глобально уникален** среди всех транзакций (`transactions.idempotency_key` UNIQUE). В миграции зафиксировано возможное усиление до scoped idempotency `(customer_id, key)`.
- Повтор с тем же ключом возвращает **существующую** транзакцию: HTTP **200**; первое создание — **201**.
- Тело запроса при повторе **не сверяется** с первой операцией (MVP): совпадение ключа достаточно, чтобы вернуть сохранённый агрегат.

`TransactionService::createTransactionOnce()`:

1. Поиск по ключу.
2. При отсутствии — DB-транзакция с `lockForUpdate` по ключу и INSERT.
3. UNIQUE в PostgreSQL — финальная защита при гонке параллельных запросов.

При idempotent replay (`TransactionCreationResult::created === false`):

- **не** dispatch'ится повторно `ProcessTransactionJob`;
- **не** пишется повторно outbox-событие `TransactionCreated` (см. [ADR-006](./adr-006-outbox-pattern.md)).

### Web UI (Inertia)

Контракт idempotency для внешних клиентов — **REST API**. В web-формах ключ генерируется на сервере (`web-deposit-{uuid}` и аналоги) на каждый submit; это не клиентская идемпотентность уровня API.

### Хранение и удержание ключей

При создании транзакции выставляется `idempotency_expires_at`:

- TTL: `config('ledgerpay.idempotency.ttl_hours')` (env `LEDGERPAY_IDEMPOTENCY_TTL_HOURS`, по умолчанию 24 часа).

Очистка истёкших ключей:

- Artisan: `idempotency:prune-expired` (пакет `--limit`);
- планировщик: ежедневно, `withoutOverlapping`.

Условия очищения ключей (prune): статус `completed`, `failed` или `cancelled` и `idempotency_expires_at < now()`. Ключ заменяется на `expired-{transaction_uuid}`, `idempotency_expires_at` обнуляется — UNIQUE освобождается для повторного использования ключа клиентом, строка транзакции сохраняется для аудита.

Транзакции в `pending` / `processing` **не** очищаются по TTL, пока не перейдут в терминальный статус.

Дополнительно: лимиты запросов (rate limiting) на движение денег (money movement) — [ADR-008](./adr-008-rate-limiting-abuse-protection.md).

## Последствия

Плюсы:

- безопасные повторы со стороны API-клиента;
- предотвращение дубликатов и повторной постановки в очередь;
- предсказуемое поведение API (201 vs 200);
- явный TTL и автоматическая очистка ключей.

Минусы:

- глобальный UNIQUE — разные клиенты не могут независимо использовать один и тот же строковый ключ;
- нет проверки совпадения payload при replay;
- после prune клиент может переиспользовать ключ для **новой** операции;
- web UI не даёт той же семантики, что заголовок у API.
