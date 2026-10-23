# Журнал изменений

Здесь фиксируются заметные изменения LedgerPay.

## [Unreleased]

### Добавлено

- Аккаунт-сегментированные Redis-очереди для `ProcessTransactionJob` (`TransactionQueueRouter`, `transactions-account-XX`, env `LEDGERPAY_TRANSACTION_QUEUE_SHARDS`).
- Laravel Horizon: супервизоры для сегментированных очередей обработки транзакций, outbox и default; Makefile-цели `horizon`, `horizon-status`, `horizon-terminate`.
- Диагностика backlog очереди с учётом всех сегмент-очередей ([ADR-011](docs/architecture/adr-011-account-sharded-transaction-queues.md)).
- Feature-тесты `TransactionQueueRoutingTest`.
- Набор тестов конкурентности на PostgreSQL (`tests/Concurrency/`, PHPUnit suite `Concurrency`): параллельные списания, встречные переводы, гонка по ключу идемпотентности, двойная обработка одной транзакции.
- Cursor pagination для API (`GET /transactions`, `GET /accounts/{uuid}/ledger`) и backoffice-листов; `CursorPaginationPayload`, конфиг `ledgerpay.pagination`.
- Индексы под `ORDER BY id DESC` для cursor-пагинации.
- Документ `docs/architecture/partitioning-plan.md`, ADR-012, cutover pipeline (`PartitionCatalog`, `PartitionCutover`, Artisan `partitions:prepare|backfill|validate|swap|cutover|ensure`, scheduler `partitions:ensure`).
- Feature-тест `CursorPaginationTest`.
- Снимки баланса (`account_balance_snapshots`), Artisan `ledger:snapshot-balances`, поэтапная сверка ([ADR-013](docs/architecture/adr-013-balance-snapshots-incremental-reconciliation.md)); feature-тест `BalanceSnapshotsTest`.

## [2.0.0] — Portfolio Release

### Добавлено

- Laravel-архитектура в духе DDD (Domain / Application / Http)
- Домен клиентов и счетов
- Хранение денег в минорных единицах (integer)
- Сценарии deposit, withdrawal, transfer
- Блокировки строк PostgreSQL (`lockForUpdate`)
- Неизменяемый реестр (debit/credit)
- Асинхронная обработка транзакций через Redis Queue
- Ключи идемпотентности для движения средств
- API-аутентификация Sanctum
- Веб-сессии (Breeze)
- Policies для счетов и транзакций
- Backoffice (операторская панель)
- Audit log (журнал аудита)
- Outbox pattern (исходящие события)
- Типизированные доменные события
- Отчёты сверки балансов (reconciliation)
- Ошибки API в формате Problem Details (`application/problem+json`, стиль RFC 7807)
- Correlation ID запросов (`X-Request-Id`)
- Rate limiting и security headers
- CHECK constraints и триггеры PostgreSQL
- Версионирование API: `/api/v1`
- Inertia + Vue 3 dashboard
- Health, readiness и диагностика (liveness / readiness)
- Реалистичный demo seed
- OpenAPI и Swagger UI
- CI pipeline
- Quality gates: Pint, PHPStan/Larastan, Rector
- Опциональная публикация outbox-событий в Kafka/Redpanda при `KAFKA_ENABLED=true`

### Примечания

Релиз 2.0.0 оформлен как portfolio backend для собеседований (уровень Senior Backend / Tech Lead). Это не production-релиз платёжной системы.
