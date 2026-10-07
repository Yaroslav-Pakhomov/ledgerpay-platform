# Документация LedgerPay

## Основное

- [README](../README.md)
- [Журнал изменений (CHANGELOG)](../CHANGELOG.md)
- [Политика безопасности](../SECURITY.md)
- [Подробная архитектура](../README_ARCHITECTURE.md)
- [Quality gates (качество кода)](quality.md)
- [Версионирование API](api-versioning.md)
- [Оглавление ADR](architecture/README.md)

## Собеседование

- [Сценарий для собеседования](interview/walkthrough.md)

## Релиз

- [Чеклист релиза](release/release-checklist.md)

## OpenAPI

- [Спецификация OpenAPI](openapi/ledgerpay.openapi.yaml)

## Архитектурные решения (ADR)

- [ADR-001: Хранение денег в минорных единицах](architecture/adr-001-money-as-minor-units.md)
- [ADR-002: Неизменяемый ledger](architecture/adr-002-immutable-ledger.md)
- [ADR-003: Асинхронная обработка транзакций](architecture/adr-003-async-transaction-processing.md)
- [ADR-004: Ключи идемпотентности](architecture/adr-004-idempotency.md)
- [ADR-005: Усиление целостности на уровне PostgreSQL](architecture/adr-005-database-hardening.md)
- [ADR-006: Outbox (исходящие события)](architecture/adr-006-outbox-pattern.md)
- [ADR-007: Сверка балансов и реестра](architecture/adr-007-reconciliation.md)
- [ADR-008: Ограничение частоты запросов (rate limiting) и защита от злоупотреблений](architecture/adr-008-rate-limiting-abuse-protection.md)
- [ADR-009: Типизированные доменные события](architecture/adr-009-typed-domain-events.md)
- [ADR-010: Состояние, готовность и диагностика](architecture/adr-010-health-readiness-diagnostics.md)
