# Архитектурная документация

| Документ                                                                                 | Описание                                                           |
|------------------------------------------------------------------------------------------|--------------------------------------------------------------------|
| [context.md](./context.md)                                                               | Контекстная диаграмма системы                                      |
| [ADR-001: Money as minor units](./adr-001-money-as-minor-units.md)                       | Деньги в минорных единицах (integer)                               |
| [ADR-002: Immutable ledger](./adr-002-immutable-ledger.md)                               | Неизменяемый финансовый журнал                                     |
| [ADR-003: Async transaction processing](./adr-003-async-transaction-processing.md)       | Асинхронная обработка транзакций                                   |
| [ADR-004: Idempotency](./adr-004-idempotency.md)                                         | Ключи идемпотентности, TTL и `idempotency:prune-expired`           |
| [ADR-005: Database hardening](./adr-005-database-hardening.md)                           | CHECK constraints, immutability triggers, indexes                  |
| [Query Plan Inspector](../database/query-plan-inspector.md)                              | EXPLAIN / EXPLAIN ANALYZE для проверки индексов                    |
| [swagger.md](./swagger.md)                                                               | OpenAPI + Swagger UI: архитектура и сценарий Try it out            |
| [api-versioning.md](../api-versioning.md)                                                | Версионирование REST API (`/api/v1`, legacy aliases)               |
| [ADR-006: Outbox (исходящие события)](./adr-006-outbox-pattern.md)                       | Transactional outbox для надёжной публикации доменных событий      |
| [ADR-007: Сверка балансов](./adr-007-reconciliation.md)                                  | Сверка сохранённого баланса с балансом, восстановленным из реестра |
| [ADR-008: Ограничение частоты запросов (rate limiting)](./adr-008-rate-limiting-abuse-protection.md) | Лимиты запросов и security headers                                 |
| [ADR-009: Типизированные доменные события](./adr-009-typed-domain-events.md)           | Типизированные доменные события и преобразователь outbox          |
| [ADR-010: Состояние, готовность и диагностика](./adr-010-health-readiness-diagnostics.md) | Работоспособность/Готовность (Liveness/readiness), CLI и страница диагностики бэк-офиса        |
| [ADR-011: Account-sharded queues (Аккаунт-сегментированные очереди) + Horizon](./adr-011-account-sharded-transaction-queues.md) | Shard-очереди `transactions-account-XX`, routing, Horizon workers |
| [ADR-012: Cursor pagination и партиционирование](./adr-012-cursor-pagination-partitioning.md) | Cursor-листы, индексы, cutover CLI (`partitions:*`), [partitioning-plan](./partitioning-plan.md) |
| [План партиционирования](./partitioning-plan.md) | Range-партиционирование append-only таблиц, миграция через shadow-таблицу |
| [ADR-013: Снимки баланса и поэтапная сверка](./adr-013-balance-snapshots-incremental-reconciliation.md) | Неизменяемые снимки баланса, `ledger:snapshot-balances`, инкремент сверки в metadata отчёта |
| [ADR-014: Проекции для чтения (read models)](./adr-014-read-model-projections.md) | Таблицы `account_summary_read_models` / `transaction_summary_read_models`, projectors, `read-models:rebuild` |

См. также [OpenAPI spec](../openapi/ledgerpay.openapi.yaml) и [README_ARCHITECTURE.md](../../README_ARCHITECTURE.md).
