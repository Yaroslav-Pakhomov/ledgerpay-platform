# ADR-012: Cursor pagination и стратегия партиционирования

## Статус

Принято.

## Контекст

Offset pagination (`LIMIT/OFFSET`) на больших таблицах деградирует: СУБД вынуждена просканировать и отбросить много строк.

Append-only таблицы LedgerPay (`ledger_entries`, `audit_logs`, `outbox_messages`, `reconciliation_reports`) растут непрерывно; без партиционирования усложняются резервное копирование, удержание данных и архив.

После [ADR-011](./adr-011-account-sharded-transaction-queues.md) улучшена асинхронная обработка; следующий узкий участок — **чтение больших списков** в API и backoffice.

## Решение

### Cursor pagination

Для endpoints со списками большого объёма:

- сортировка **`ORDER BY id DESC`** (`latest('id')`) — стабильный cursor;
- Laravel **`cursorPaginate()`**;
- API: единая JSON-обёртка через `CursorPaginationPayload` (`data` + `meta.*_cursor`);
- лимиты: `config('ledgerpay.pagination')`, query `limit`, верхняя граница `max_limit` (clamp в `cursorLimit()`, не 422).

Затронутые поверхности:

- API: `GET /transactions`, `GET /accounts/{uuid}/ledger`;
- Backoffice: transactions, audit logs, outbox, reconciliation (Inertia + `CursorPagination.vue`).

### Партиционирование (tooling + runbook, не auto-deploy)

- Runbook: [partitioning-plan.md](./partitioning-plan.md).
- Реализация cutover: `PartitionCatalog`, `PartitionCutover`, Artisan `partitions:{prepare,backfill,validate,swap,cutover,ensure}`.
- Партиции **не создаются** при обычном `migrate`; переключение — операционная процедура на PostgreSQL.
- Ключ партиции: `created_at` (RANGE, месяц); PK shadow: `(id, created_at)`.
- Обслуживание: daily `partitions:ensure … --months=2` для четырёх таблиц (`routes/console.php`).
- Индексы `(id DESC)` и `(account_id, id DESC)` под cursor-пагинацию.

## Последствия

Плюсы:

- предсказуемая производительность листинга на больших объёмах;
- меньше дорогих запросов с большим OFFSET;
- понятный путь к архивированию через `DETACH PARTITION`;
- исполняемый cutover pipeline в репозитории (не только документ);
- сильный portfolio-нарратив про high-load storage design.

Минусы:

- cursor не даёт произвольного перехода «на страницу N»;
- ломающее изменение формы list-ответа API (нет `links`);
- composite PK `(id, created_at)` — компромисс PostgreSQL; формальный constraint partition-local;
- swap требует короткого окна с блокировкой и остановкой INSERT (maintenance mode);
- reconciliation UI: если сортировка по `checked_at` важнее `id`, нужен явный составной порядок (`checked_at DESC, id DESC`).

## Связанные документы

- [ADR-005: Database hardening](./adr-005-database-hardening.md)
- [ADR-011: Account-sharded queues](./adr-011-account-sharded-transaction-queues.md)
- [partitioning-plan.md](./partitioning-plan.md)
