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

`app/Application/Transaction/Jobs/ProcessTransactionJob.php`

Объяснить:

- HTTP создаёт транзакцию в статусе pending
- worker выполняет движение денег
- ошибки переводят транзакцию в failed
- в backoffice можно retry

## 6. Показать целостность данных

Открыть миграции:

- `database/migrations/2026_08_26_125443_add_database_constraints_to_fintech_tables.php`
- `database/migrations/2026_08_27_063656_add_immutability_triggers_to_ledger_and_audit_logs.php`
- `database/migrations/2026_08_28_053850_add_fintech_performance_indexes.php`

Объяснить:

- CHECK constraints (ограничения PostgreSQL)
- триггер неизменяемости реестра (ledger)
- триггер неизменяемости аудита (audit)
- partial indexes (частичные индексы)

## 7. Показать outbox

Открыть:

- `app/Domain/Transaction/Events`
- `app/Application/Outbox`

Объяснить:

- типизированные доменные события
- outbox не теряет события при сбое после commit
- publisher позже можно заменить на Kafka / SNS / RabbitMQ
- при `KAFKA_ENABLED=true` — опционально Redpanda/Kafka (локальный demo)

## 8. Показать сверку

Открыть:

`app/Application/Reconciliation/Services/ReconciliationService.php`

Объяснить:

- `accounts.balance` сравнивается с балансом из ledger
- отчёты о расхождении помогают расследованию
- плановая сверка: `reconciliation:run`, scheduler, UI backoffice

## 9. Показать безопасность

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

## 10. Показать observability (наблюдаемость)

Открыть:

- `app/Application/Diagnostics`
- `app/Application/Audit/Services/AuditLogger.php`

Объяснить:

- correlation / request ID (`X-Request-Id`)
- структурированные логи
- audit trail
- readiness checks: `/api/v1/health/ready`
- страница диагностики: `/backoffice/diagnostics`

## 11. Показать тесты

Открыть:

`tests/Feature` и `tests/Concurrency`

Выделить:

- deposit, withdrawal, transfer — `TransactionProcessingTest`
- недостаточно средств, idempotency — там же
- неизменяемый ledger — `LedgerImmutabilityTest`
- ограничения PostgreSQL — `DatabaseHardeningTest`
- авторизация — `BackofficeAccessTest`
- outbox — `OutboxPatternTest`
- сверка — `ReconciliationTest`
- диагностика — `DiagnosticsTest`
- **конкурентность (несколько PHP-процессов, PostgreSQL)** — `tests/Concurrency/TransactionConcurrencyTest`: параллельные списания, встречные переводы, гонка ключа идемпотентности

## Завершение (рекомендуемая формулировка)

LedgerPay — не банковская платформа «под прод», а сфокусированный проект, который демонстрирует инженерные практики для надёжных fintech backend-систем.
