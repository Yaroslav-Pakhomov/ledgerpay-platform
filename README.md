# LedgerPay

![CI](https://github.com/Yaroslav-Pakhomov/ledgerpay-platform/actions/workflows/ci.yml/badge.svg)

**Fintech backend / portfolio project built with Laravel**

LedgerPay — backend-система для работы с клиентами, счетами и денежными операциями.

Проект сфокусирован не только на CRUD, а на backend-задачах, характерных для финансовых систем:

* идемпотентность денежных операций;
* транзакционная целостность;
* конкурентный доступ к балансам;
* асинхронная обработка;
* immutable ledger;
* audit log;
* API-контракты;
* автоматическое тестирование и статический анализ.

> LedgerPay — portfolio-проект, а не production-платёжная система.
> Его цель — демонстрация инженерных подходов к проектированию backend-систем.

---

## Содержание

* [Tech Stack](#tech-stack)
* [Core Features](#core-features)
* [Architecture](#architecture)
* [Financial Consistency](#financial-consistency)
* [Async Processing](#async-processing)
* [Ledger and Audit](#ledger-and-audit)
* [Authentication and Authorization](#authentication-and-authorization)
* [REST API](#rest-api)
* [API Errors and Observability](#api-errors-and-observability)
* [Testing and Code Quality](#testing-and-code-quality)
* [CI](#ci)
* [Local Development](#local-development)
* [Engineering Decisions](#engineering-decisions)
* [Possible Next Steps](#possible-next-steps)
* [Documentation](#documentation)

---

## Tech Stack

### Backend

* PHP 8.5+
* Laravel 13
* Laravel Sanctum
* PostgreSQL
* Redis Queue

### Frontend / Backoffice

* Vue 3
* Inertia.js
* TailwindCSS

### Quality & Infrastructure

* Docker / Laravel Sail
* PHPUnit / Feature Tests
* PHPStan / Larastan
* Rector
* Laravel Pint
* GitHub Actions
* OpenAPI / Swagger

---

## Core Features

Система поддерживает:

* регистрацию и аутентификацию пользователей;
* клиентов;
* счета;
* пополнение счёта;
* вывод средств;
* переводы между счетами;
* просмотр баланса;
* историю транзакций;
* immutable ledger;
* audit log;
* административный backoffice.

Основные операции движения денег:

```text
deposit
withdraw
transfer
```

---

## Architecture

Код разделён на основные слои:

```text
app/
├── Domain/
│   ├── Account/
│   ├── Audit/
│   ├── Customer/
│   ├── Ledger/
│   ├── Shared/
│   └── Transaction/
│
├── Application/
│   ├── Account/
│   ├── Audit/
│   ├── Auth/
│   ├── Customer/
│   └── Transaction/
│
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
│
└── Support/
```

### Основной поток write-операции

```text
HTTP Request
    ↓
Form Request
    ↓
DTO
    ↓
Application Service
    ↓
Domain
    ↓
Database Transaction
    ↓
Queue Job
    ↓
Transaction Processor
    ↓
Account Balance + Ledger + Audit
```

Основные принципы:

* Domain-слой не зависит от HTTP;
* HTTP-контроллеры используются как transport layer;
* validation отделена от бизнес-логики;
* данные передаются через typed DTO;
* бизнес-операции выполняются через Application Services;
* критичные изменения данных выполняются внутри DB transactions.

Подробное описание архитектуры:

[README_ARCHITECTURE.md](./README_ARCHITECTURE.md)

---

## Financial Consistency

### Money Representation

Денежные значения хранятся в **minor units**.

Например:

```text
100.50 RUB → 10050
```

Это позволяет не использовать `float` для финансовых расчётов и избегать ошибок двоичного округления.

---

### Idempotency

Операции движения денег требуют HTTP-заголовок:

```http
Idempotency-Key
```

Он используется для:

* защиты от повторного создания одной операции;
* безопасного retry со стороны клиента;
* предотвращения повторной постановки одной операции в очередь.

Например, если клиент повторит запрос из-за network timeout с тем же:

```http
Idempotency-Key: transfer-123
```

новая финансовая операция создаваться не должна.

На уровне БД используется unique constraint для `idempotency_key`.

Упрощённая схема:

```text
Request
   ↓
Idempotency-Key
   ↓
Existing transaction?
   ├── Yes → return existing transaction
   └── No  → create transaction
```

---

### Concurrency Control

Обработка денежных операций выполняется внутри database transaction.

Для защиты от конкурентного изменения балансов используются pessimistic row locks:

```php
lockForUpdate()
```

При переводе между счетами блокируются оба счёта.

```text
Account A
    ↓ lock

Account B
    ↓ lock

Validate balances
    ↓

Debit / Credit
```

Счета блокируются в стабильном порядке по ID.

Это снижает вероятность deadlock при конкурентных переводах:

```text
Request 1: Account A → Account B
Request 2: Account B → Account A
```

Основным механизмом обеспечения консистентности являются ограничения и блокировки на уровне БД, а не только проверки в PHP-коде.

---

## Async Processing

HTTP request-response cycle отделён от фактического выполнения денежной операции.

При создании операции сначала сохраняется транзакция в состоянии:

```text
Pending
```

После этого dispatch-ится:

```text
ProcessTransactionJob
```

в Redis Queue.

### HTTP flow

```text
POST /api/transactions/transfer
        ↓
Validate request
        ↓
Check Idempotency-Key
        ↓
Create Pending transaction
        ↓
Dispatch ProcessTransactionJob
        ↓
Return HTTP response
```

### Worker flow

```text
ProcessTransactionJob
        ↓
Start DB transaction
        ↓
Lock transaction
        ↓
Lock account(s)
        ↓
Validate domain rules
        ↓
Debit / Credit
        ↓
Write ledger entries
        ↓
Write audit event
        ↓
Completed
```

Состояния транзакции:

```text
Pending
   ↓
Processing
   ↓
Completed
```

При ошибке:

```text
Processing
   ↓
Failed
```

Такой подход уменьшает объём тяжёлой работы внутри HTTP-запроса и позволяет независимо обрабатывать финансовые операции worker-процессами.

---

## Ledger and Audit

### Immutable Ledger

Каждое фактическое движение денег фиксируется отдельной записью в ledger.

`LedgerEntry` используется как append-only модель.

После создания записи запрещены:

* update;
* delete.

Ledger хранит:

* счёт;
* связанную транзакцию;
* направление движения;
* сумму;
* balance after operation.

Для transfer создаются две записи:

```text
Source Account
    ↓
Debit Ledger Entry

Target Account
    ↓
Credit Ledger Entry
```

Баланс счёта представляет текущее состояние.

Ledger представляет историю движения средств.

---

### Audit Log

Отдельный append-only `AuditLog` используется для фиксации действий приложения и пользователей.

В audit могут записываться:

* authentication events;
* создание счетов;
* результаты обработки транзакций;
* административные действия;
* request ID.

Audit-записи также не должны изменяться или удаляться через модель после создания.

---

## Authentication and Authorization

REST API использует Laravel Sanctum.

Поддерживаются:

```text
POST /api/auth/register
POST /api/auth/login
GET  /api/auth/me
POST /api/auth/logout
```

После успешной аутентификации клиент работает с API через Bearer token.

Доступ к бизнес-ресурсам контролируется через Laravel Policies.

Клиент может работать только с доступными ему ресурсами, тогда как backoffice имеет расширенные права.

---

## REST API

### Authentication

```text
POST   /api/auth/register
POST   /api/auth/login
GET    /api/auth/me
POST   /api/auth/logout
```

### Customers

```text
GET    /api/customers
POST   /api/customers
GET    /api/customers/{uuid}
```

### Accounts

```text
GET    /api/accounts
POST   /api/accounts
GET    /api/accounts/{uuid}
GET    /api/accounts/{uuid}/balance
GET    /api/accounts/{uuid}/ledger
```

### Transactions

```text
GET    /api/transactions
POST   /api/transactions/deposit
POST   /api/transactions/withdraw
POST   /api/transactions/transfer
POST   /api/transactions/{uuid}/retry
GET    /api/transactions/{uuid}
```

Полный API-контракт описан в OpenAPI specification:

[ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml)

---

### Example Request

Получение Bearer token:

```text
POST /api/auth/login
```

После авторизации можно выполнить денежную операцию.

Пример deposit:

```bash
curl -X POST http://localhost/api/transactions/deposit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -H "Idempotency-Key: deposit-demo-001" \
  -d '{
    "target_account_uuid": "<uuid>",
    "amount": 100000,
    "currency": "RUB"
  }'
```

---

## API Errors and Observability

API использует структуру ошибок в стиле **Problem Details**.

Для корреляции HTTP-запросов используется:

```http
X-Request-Id
```

Request ID:

* принимается от клиента или генерируется приложением;
* возвращается клиенту в HTTP response;
* используется в логировании;
* связывается с audit events.

Это позволяет связать:

```text
HTTP Request
    ↓
Application Logs
    ↓
Audit Event
```

по одному идентификатору запроса.

---

## Testing and Code Quality

### Feature Tests

Проект содержит Feature Tests для основных контрактов системы.

Проверяются, в частности:

* authentication;
* accounts;
* customers;
* authorization;
* создание транзакций;
* validation;
* idempotency;
* asynchronous processing;
* ledger immutability;
* audit log;
* API error handling;
* `X-Request-Id`;
* API documentation.

Запуск тестов:

```bash
./vendor/bin/sail artisan test
```

---

### PHPStan / Larastan

Статический анализ (level 6):

```bash
composer stan          # alias: composer phpstan
make stan
```

---

### Laravel Pint

```bash
composer pint          # fix dirty files
composer pint:test     # check all (CI)
make pint-test
```

---

### Rector

```bash
composer rector:test   # dry-run (CI)
composer rector        # apply locally
make rector-test
```

---

### Full Quality Check

```bash
composer quality       # pint + stan + rector
composer ci            # + tests (CI gate)
make ci                # + frontend build
```

Подробнее: [docs/quality.md](./docs/quality.md).

---

## CI

GitHub Actions запускается для push и pull request в `develop`, `master`, `feature/**`.

Pipeline включает:

```text
Composer dependencies
        ↓
Database migrations
        ↓
Rector dry-run
        ↓
Laravel Pint
        ↓
PHPStan
        ↓
PHPUnit / Feature Tests
        ↓
Frontend build
```

Таким образом изменения автоматически проверяются на:

* корректность тестов;
* статический анализ;
* code style;
* соответствие refactoring rules;
* возможность сборки frontend.

---

## Local Development

### Requirements

Необходимы:

* Docker;
* Docker Compose;
* Composer.

---

### Installation

```bash
git clone https://github.com/Yaroslav-Pakhomov/ledgerpay-platform.git
cd ledgerpay-platform

cp .env.example .env

composer install

./vendor/bin/sail up -d

./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Для frontend development:

```bash
./vendor/bin/sail npm run dev
```

---

### Queue Worker

Транзакции обрабатываются через Redis Queue.

Запуск worker:

```bash
./vendor/bin/sail artisan queue:work redis --queue=transactions,outbox,default
```

Для outbox также нужен scheduler:

```bash
./vendor/bin/sail artisan schedule:work
```

---

### Run Tests

```bash
./vendor/bin/sail artisan test
```

---

### Swagger / OpenAPI

Для локальной разработки:

```env
API_DOCS_ENABLED=true
```

Swagger UI:

http://localhost/api/docs

В production API documentation рекомендуется отключать:

```env
API_DOCS_ENABLED=false
```

---

## Engineering Decisions

В проекте осознанно используются следующие решения.

### Minor units instead of float

Денежные значения представлены целыми числами, чтобы избежать ошибок floating-point arithmetic.

### Database transactions

Изменения финансового состояния выполняются атомарно внутри транзакций БД.

### Pessimistic locking

`lockForUpdate()` защищает баланс от конкурентного изменения несколькими worker-процессами.

### Stable lock ordering

Счета блокируются в стабильном порядке, что снижает вероятность deadlock.

### Idempotency keys

Повторный HTTP-запрос не должен приводить к созданию второй денежной операции.

### Async processing

HTTP endpoint создаёт операцию и ставит её в очередь, а фактическая обработка выполняется worker-процессом.

### Immutable ledger

Ledger используется как append-only история реального движения средств.

### Immutable audit log

Audit хранит историю действий приложения и пользователей.

### Database-level invariants

Критичные финансовые инварианты enforced в PostgreSQL:

- non-negative account balances;
- positive transaction amounts;
- valid transaction account shape (deposit / withdrawal / transfer);
- immutable ledger entries (DB triggers);
- immutable audit logs (DB triggers);
- partial indexes для мониторинга failed/pending транзакций.

Это защищает систему даже при обходе application-level validation.

Подробнее: [ADR-005](./docs/architecture/adr-005-database-hardening.md).

### Outbox pattern

LedgerPay записывает доменные события в `outbox_messages` в той же DB-транзакции, что и бизнес-изменение.

События транзакции:

- `transaction.created` — операция заведена (Pending);
- `transaction.completed` — деньги успешно обработаны;
- `transaction.failed` — терминальный сбой после retry.

Отдельная команда dispatch'ит pending-сообщения в queue workers.

Это предотвращает классическую проблему: DB commit успешен, а публикация события — нет.

Подробнее: [ADR-006](./docs/architecture/adr-006-outbox-pattern.md).

### Typed DTO

Transport data отделена от бизнес-логики.

### Thin controllers

Контроллеры отвечают за HTTP-level orchestration, а бизнес-операции выполняются Application Services.

### Laravel Policies

Authorization вынесена из контроллеров в отдельный механизм доступа.

### Problem Details

API возвращает структурированные ошибки.

### Correlation IDs

`X-Request-Id` связывает HTTP request, application logs и audit events.

### Static analysis and automated tests

PHPStan, PHPUnit, Pint и Rector используются как часть автоматической проверки качества кода.

---

### Conscious Trade-offs

Проект сознательно не усложнён преждевременно такими подходами, как:

* Event Sourcing;
* CQRS;
* Saga;
* отдельный Repository layer для каждой модели.

Эти подходы имеют смысл при соответствующей сложности системы, но не должны использоваться только ради архитектурных паттернов.

Подробное объяснение архитектурных решений и компромиссов:

[README_ARCHITECTURE.md](./README_ARCHITECTURE.md)

---

## Possible Next Steps

Возможные направления развития проекта:

- **Scoped idempotency per customer** — отдельная область `Idempotency-Key` для каждого клиента.
- **API rate limiting** — ограничение частоты запросов к API.
- **Sanctum token abilities** — разграничение прав доступа для API-токенов.
- **Transactional outbox** — надёжная отправка событий во внешние системы без потери сообщений.
- **Reconciliation job** — периодическая сверка ledger с текущими балансами счетов.
- **Query/read services** — отдельный слой для сложных выборок и отчётности.
- **Metrics** — сбор технических и бизнес-метрик системы.
- **Distributed tracing** — трассировка запросов через API, очередь и worker.
- **Load tests** — проверка системы под высокой нагрузкой.
- **Concurrency tests** — проверка корректности конкурентных операций и блокировок.

---

## Documentation

| Документ                      | Ссылка                                                          |
| ----------------------------- | --------------------------------------------------------------- |
| Swagger UI                    | http://localhost/api/docs                                       |
| OpenAPI specification         | [ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml) |
| Swagger architecture          | [swagger.md](./docs/architecture/swagger.md)                    |
| Architecture Decision Records | [docs/architecture/README.md](./docs/architecture/README.md)    |
| Context diagram               | [context.md](./docs/architecture/context.md)                    |
| Code quality guide            | [quality.md](./docs/quality.md)                                 |
| Query plan inspector            | [query-plan-inspector.md](./docs/database/query-plan-inspector.md) |
| Detailed architecture         | [README_ARCHITECTURE.md](./README_ARCHITECTURE.md)              |

---

## Project Goal

LedgerPay предназначен для демонстрации подходов к backend-разработке, в частности:

* проектирования REST API;
* организации бизнес-логики;
* транзакционной целостности;
* идемпотентности;
* конкурентного доступа к данным;
* асинхронной обработки;
* проектирования ledger и audit;
* автоматического тестирования;
* статического анализа;
* CI;
* документирования архитектурных решений.
