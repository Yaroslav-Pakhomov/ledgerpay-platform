# Архитектурная документация

| Документ                                                                                 | Описание                                                           |
|------------------------------------------------------------------------------------------|--------------------------------------------------------------------|
| [context.md](./context.md)                                                               | Контекстная диаграмма системы                                      |
| [ADR-001: Money as minor units](./adr-001-money-as-minor-units.md)                       | Деньги в минорных единицах (integer)                               |
| [ADR-002: Immutable ledger](./adr-002-immutable-ledger.md)                               | Неизменяемый финансовый журнал                                     |
| [ADR-003: Async transaction processing](./adr-003-async-transaction-processing.md)       | Асинхронная обработка транзакций                                   |
| [ADR-004: Idempotency](./adr-004-idempotency.md)                                         | Ключи идемпотентности для движения средств                         |
| [ADR-005: Database hardening](./adr-005-database-hardening.md)                           | CHECK constraints, immutability triggers, indexes                  |
| [Query Plan Inspector](../database/query-plan-inspector.md)                              | EXPLAIN / EXPLAIN ANALYZE для проверки индексов                    |
| [swagger.md](./swagger.md)                                                               | OpenAPI + Swagger UI: архитектура и сценарий Try it out            |
| [ADR-006: Outbox pattern](./adr-006-outbox-pattern.md)                                   | Transactional outbox для надёжной публикации доменных событий      |
| [ADR-007: Сверка балансов](./adr-007-reconciliation.md)                                  | Сверка сохранённого баланса с балансом, восстановленным из реестра |
| [ADR-008: Rate limiting и abuse protection](./adr-008-rate-limiting-abuse-protection.md) | Лимиты запросов, security headers, retention idempotency keys      |
| [ADR-009: Типизированные доменные события](./adr-009-typed-domain-events.md)           | Типизированные доменные события и преобразователь outbox          |

См. также [OpenAPI spec](../openapi/ledgerpay.openapi.yaml) и [README_ARCHITECTURE.md](../../README_ARCHITECTURE.md).
