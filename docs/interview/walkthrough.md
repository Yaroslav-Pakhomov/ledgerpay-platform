# Сценарий для собеседования

Краткий маршрут, как показывать LedgerPay на backend-собеседовании.

## 1. Сформулировать задачу

LedgerPay — компактная fintech-система учёта и движения денег.

На примере видно, как проектировать **безопасное** движение средств:

- пополнения (deposit)
- списания (withdrawal)
- переводы (transfer)
- балансы счетов
- неизменяемый ledger (журнал проводок)
- асинхронная обработка
- идемпотентность (idempotency)
- аудит (audit log)
- сверка (reconciliation)
- cursor pagination и план партиционирования append-only таблиц

## 2. Показать архитектуру

Открыть:

```text
app/Domain
app/Application
app/Http
```

Объяснить:

- **Domain** — бизнес-понятия и модели
- **Application** — use cases и оркестрация
- **Http** — транспорт (контроллеры, middleware)
- инфраструктурные детали изолированы от домена

## 3. Показать движение денег

Открыть:

`app/Application/Transaction/Services/TransactionProcessorService.php`

Объяснить:

- `DB::transaction`
- `lockForUpdate`
- детерминированный порядок блокировок при transfer
- без float — только minor units (минорные единицы)
- неизменяемые проводки ledger
- жизненный цикл транзакции (pending → completed / failed)

## 4. Показать идемпотентность

Открыть:

`app/Application/Transaction/Services/TransactionService.php`

Объяснить:

- клиенты повторяют запросы при сетевых сбоях
- idempotency key не даёт зачислить/списать дважды
- UNIQUE в БД — последняя линия защиты
- у ключей есть срок хранения (retention)

## 5. Показать асинхронность

Открыть:

- `app/Application/Transaction/Services/TransactionService.php` (dispatch)
- `app/Application/Transaction/Routing/TransactionQueueRouter.php`
- `app/Application/Transaction/Jobs/ProcessTransactionJob.php`

Объяснить:

- HTTP создаёт транзакцию в статусе pending
- job попадает в shard-очередь `transactions-account-XX` по routing account (deposit → target, withdrawal/transfer → source)
- **Laravel Horizon** обрабатывает shards + `outbox` + `default`; UI `/horizon` для backoffice (web-сессия)
- worker выполняет движение денег; приоритетная последовательность — `lockForUpdate` в процессе, не только очередь
- ошибки переводят транзакцию в failed; в бэк-офисе можно повторить (повторный dispatch в тот же shard)

Подробнее: [ADR-003](../architecture/adr-003-async-transaction-processing.md), [ADR-011](../architecture/adr-011-account-sharded-transaction-queues.md).

## 6. Показать чтение под нагрузкой (cursor pagination + partitioning)

Открыть:

- `app/Http/Requests/Common/CursorPaginatedRequest.php`
- `app/Http/Requests/Concerns/UsesCursorPagination.php`
- `app/Support/Pagination/CursorPaginationPayload.php`
- `app/Http/Controllers/Api/TransactionController.php` (метод `index`)
- `resources/js/Components/UI/CursorPagination.vue`
- `docs/architecture/partitioning-plan.md`
- `app/Console/Partitioning/PartitionCatalog.php`
- `app/Console/Partitioning/PartitionCutover.php`
- `app/Console/Commands/CutoverTablePartitionsCommand.php`
- `app/Console/Commands/EnsureTablePartitionsCommand.php`
- `routes/console.php` (schedule `partitions:ensure`)

Объяснить:

- offset pagination на больших таблицах дорогая; для списков transactions / ledger / backoffice — **cursor pagination** с сортировкой **`id DESC`** (стабильный порядок для cursor).
- API: query `cursor`, `limit`; ответ `data` + `meta` (`per_page`, `next_cursor`, `prev_cursor`, `has_more_pages`) — **без** `links` (ломающее изменение для list endpoints).
- `limit` ограничивается конфигом `ledgerpay.pagination` (`LEDGERPAY_DEFAULT_CURSOR_LIMIT`, `LEDGERPAY_MAX_CURSOR_LIMIT`); завышенный limit **обрезается**, а не 422.
- backoffice: Inertia + Next/Previous через `prev_page_url` / `next_page_url`.
- **Партиционирование** в типичном dev **не включено в БД** — в репозитории есть **production runbook + исполняемый pipeline**: shadow `{table}_partitioned`, INSERT-триггер + backfill, validate, swap под lock, затем `partitions:ensure` по cron; staging demo: `partitions:cutover audit_logs --months=3` → `partitions:swap audit_logs --confirm-writes-stopped`; архивирование через `DETACH PARTITION`.

Подробнее: [ADR-012](../architecture/adr-012-cursor-pagination-partitioning.md), [partitioning-plan.md](../architecture/partitioning-plan.md).

Демо (API, токен backoffice):

- `GET /api/v1/transactions?limit=10`
- backoffice: `/backoffice/transactions` — листание «Далее» / «Назад»

## 7. Показать целостность данных

Открыть миграции:

- `database/migrations/2026_08_26_125443_add_database_constraints_to_fintech_tables.php`
- `database/migrations/2026_08_27_063656_add_immutability_triggers_to_ledger_and_audit_logs.php`
- `database/migrations/2026_08_28_053850_add_fintech_performance_indexes.php`
- `database/migrations/2026_10_13_050504_add_cursor_pagination_indexes.php`

Объяснить:

- CHECK constraints (ограничения PostgreSQL)
- триггер неизменяемости реестра (ledger)
- триггер неизменяемости аудита (audit)
- partial indexes (частичные индексы)
- индексы под cursor pagination (`id DESC`, `ledger_entries (account_id, id DESC)`)

## 8. Показать outbox

Открыть:

- `app/Domain/Transaction/Events`
- `app/Application/Outbox`

Объяснить:

- типизированные доменные события
- outbox не теряет события при сбое после commit
- publisher позже можно заменить на Kafka / SNS / RabbitMQ
- при `KAFKA_ENABLED=true` — опционально Redpanda/Kafka (локальный demo)

## 9. Показать сверку

Открыть:

`app/Application/Reconciliation/Services/ReconciliationService.php`

Объяснить:

- `accounts.balance` сравнивается с балансом из ledger
- отчёты о расхождении помогают расследованию
- плановая сверка: `reconciliation:run`, scheduler, UI backoffice
- при длинной истории: последний снимок + delta (`metadata.strategy`: `snapshot_incremental` / `full_ledger_scan`)
- снимки: `BalanceSnapshotService`, `account_balance_snapshots`, CLI `ledger:snapshot-balances`, backoffice `/backoffice/balance-snapshots`, [ADR-013](../architecture/adr-013-balance-snapshots-incremental-reconciliation.md)

## 10. Показать безопасность

Открыть:

- `app/Policies`
- `app/Http/Middleware`
- `routes/api_v1.php`

Объяснить:

- Sanctum для API
- policies владения счётом
- страницы только для backoffice
- rate limiting (ограничение частоты запросов)
- security headers
- ошибки Problem Details без «утечки» внутренностей

## 11. Показать observability (наблюдаемость)

Открыть:

- `app/Application/Diagnostics`
- `../../app/Application/Audit/Services/AuditLoggerService.php`

Объяснить:

- correlation / request ID (`X-Request-Id`)
- структурированные логи
- audit trail
- проверка готовности: `/api/v1/health/ready` (backlog по всем shard-очередям + outbox)
- страница диагностики: `/backoffice/diagnostics`
- мониторинг очередей: `/horizon` (backoffice)

## 12. Показать тесты

Открыть:

`tests/Feature`, `tests/Feature/Partitioning`, `tests/Unit/Partitioning` и `tests/Concurrency`

Выделить:

- deposit, withdrawal, transfer — `TransactionProcessingTest`
- недостаточно средств, idempotency — там же
- неизменяемый ledger — `LedgerImmutabilityTest`
- ограничения PostgreSQL — `DatabaseHardeningTest`
- авторизация — `BackofficeAccessTest`
- outbox — `OutboxPatternTest`
- сверка — `ReconciliationTest`
- снимки баланса и инкремент сверки — `BalanceSnapshotsTest`
- диагностика — `DiagnosticsTest`
- маршрутизация shard-очередей — `TransactionQueueRoutingTest`
- **конкурентность (несколько PHP-процессов, PostgreSQL)** — `tests/Concurrency/TransactionConcurrencyTest`: параллельные списания, встречные переводы, гонка ключа идемпотентности
- cursor pagination API — `CursorPaginationTest`
- каталог партиций — `tests/Unit/Partitioning/PartitionCatalogTest`: четыре таблицы, отказ для неизвестной, мутабелен только outbox
- команды `partitions:*` — `tests/Feature/Partitioning/TablePartitionsCommandTest`: неизвестная таблица, swap требует `--confirm-writes-stopped`
- cutover — `tests/Feature/Partitioning/PartitionCutoverTest`: prepare/backfill/validate, cutover без rename, sync outbox, сумма amount, swap и ensure; runbook [partitioning-plan.md](../architecture/partitioning-plan.md)

## Завершение (рекомендуемая формулировка)

LedgerPay — не банковская платформа «под прод», а сфокусированный проект, который демонстрирует инженерные практики для надёжных fintech backend-систем.
