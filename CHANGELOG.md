# Журнал изменений

Здесь фиксируются заметные изменения LedgerPay.

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
