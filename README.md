# LedgerPay

![CI](https://github.com/Yaroslav-Pakhomov/ledgerpay-platform/actions/workflows/ci.yml/badge.svg)

**Fintech backend / portfolio-проект на Laravel**

LedgerPay — backend-система для работы с клиентами, счетами и денежными операциями.

Проект сфокусирован не только на CRUD, а на backend-задачах, характерных для финансовых систем:

* идемпотентность денежных операций;
* транзакционная целостность;
* конкурентный доступ к балансам;
* асинхронная обработка;
* неизменяемый реестр (ledger);
* аудит (audit log);
* API-контракты;
* автоматическое тестирование и статический анализ.

> LedgerPay — portfolio-проект, а не production-платёжная система.

> Его цель — демонстрация инженерных подходов к проектированию backend-систем.

## Документация

- [Индекс документации](docs/index.md)
- [Журнал изменений (CHANGELOG)](CHANGELOG.md)
- [Политика безопасности](SECURITY.md)
- [Сценарий для собеседования](docs/interview/walkthrough.md)
- [Чеклист релиза](docs/release/release-checklist.md)
- [Архитектурные решения (ADR)](docs/index.md#архитектурные-решения-adr)
- [Подробная архитектура](README_ARCHITECTURE.md)
- [Спецификация OpenAPI](docs/openapi/ledgerpay.openapi.yaml)

---

## Содержание

* [Документация](#документация)
* [Стек технологий](#стек-технологий)
* [Основные возможности](#основные-возможности)
* [Архитектура](#архитектура)
* [Финансовая согласованность](#финансовая-согласованность)
* [Асинхронная обработка](#асинхронная-обработка)
* [Ledger и аудит](#ledger-и-аудит)
* [Аутентификация и авторизация](#аутентификация-и-авторизация)
* [REST API](#rest-api)
* [Ошибки API и наблюдаемость](#ошибки-api-и-наблюдаемость)
* [Тестирование и качество кода](#тестирование-и-качество-кода)
* [Непрерывная интеграция (CI)](#непрерывная-интеграция-ci)
* [Локальная разработка](#локальная-разработка)
* [Демо-пользователи](#демо-пользователи)
* [Демо-данные](#демо-данные)
* [Состояние и готовность](#состояние-и-готовность)
* [Инженерные решения](#инженерные-решения)
* [Возможные следующие шаги](#возможные-следующие-шаги)
* [Ссылки на документы](#ссылки-на-документы)
* [Цель проекта](#цель-проекта)

---

## Стек технологий

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

#### Frontend dashboard

Панель управления Inertia/Vue включает в себя:

- повторно используемые компоненты пользовательского интерфейса ("StatusBadge", "MoneyAmount", "EmptyState", "PageHeader", "Разбивка на страницы`)
- автоматическое обновление транзакций во время ожидания заданий в очереди ("useAutoRefresh")
- Разбивка на страницы Laravel в транзакциях панели мониторинга и бухгалтерской книге
- пустые состояния вместо пустых таблиц
- мониторинг в бэкофисе (транзакции, аудит, исходящие сообщения, сверка)

Веб-страницы инерции используют "App\Http\Resources\Account\AccountResource" и "Transaction\TransactionResource"; REST API использует `Account\Api\*` и `Transaction\Api\*`.

### Quality & Infrastructure

* Docker / Laravel Sail
* PHPUnit / Feature Tests
* PHPStan / Larastan
* Rector
* Laravel Pint
* GitHub Actions
* OpenAPI / Swagger

---

## Основные возможности

Система поддерживает:

* регистрацию и аутентификацию пользователей;
* клиентов;
* счета;
* пополнение счёта;
* вывод средств;
* переводы между счетами;
* просмотр баланса;
* историю транзакций;
* неизменяемый бух. учёт (immutable ledger);
* аудит (audit log);
* исходящие события по транзакциям (transactional outbox);
* сверку балансов счетов с реестром проводок (reconciliation);
* административный backoffice.

Основные операции движения денег:

```text
пополнение (deposit)
снятие (withdraw)
перевод (transfer)
```

---

## Архитектура

Код разделён на основные слои:

```text
app/
├── Domain/
│   ├── Account/
│   ├── Audit/
│   ├── Customer/
│   ├── Ledger/
│   ├── Outbox/
│   ├── Reconciliation/
│   ├── Shared/
│   └── Transaction/
│
├── Application/
│   ├── Account/
│   ├── Audit/
│   ├── Auth/
│   ├── Customer/
│   ├── Outbox/
│   ├── Reconciliation/
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

## Финансовая согласованность

### Денежное представление (Money Representation)

Денежные значения хранятся в **minor units**.

Например:

```text
100.50 RUB → 10050
```

Это позволяет не использовать `float` для финансовых расчётов и избегать ошибок двоичного округления.

---

### Идемпотентность (Idempotency)

Операции движения денег требуют HTTP-заголовок:

```http
Idempotency-Key
```

Он используется для:

* защиты от повторного создания одной операции;
* безопасного повтора (retry) со стороны клиента;
* предотвращения повторной постановки одной операции в очередь.

Например, если клиент повторит запрос из-за прерывания сети с тем же:

```http
Idempotency-Key: transfer-123
```

новая финансовая операция создаваться не должна.

На уровне БД используется ограничение по уникальности (unique constraint) для `idempotency_key`.

При создании задаётся срок действия ключа (`idempotency_expires_at`, TTL — `LEDGERPAY_IDEMPOTENCY_TTL_HOURS`, по умолчанию 24 ч). Для завершённых транзакций истёкшие ключи очищает команда `idempotency:prune-expired` (в scheduler — ежедневно). Подробнее: [ADR-004](./docs/architecture/adr-004-idempotency.md).

Первое создание — **HTTP 201**, idempotent replay — **200**.

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

### Контроль конкурентности (Concurrency Control)

Обработка денежных операций выполняется внутри транзакций с базой данных (database transaction).

Для защиты от конкурентного изменения балансов используются «пессимистические блокировки строк» (pessimistic row locks):

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

Это снижает вероятность взаимной блокировки (deadlock) при конкурентных переводах:

```text
Request 1: Account A → Account B
Request 2: Account B → Account A
```

Основным механизмом обеспечения консистентности являются ограничения и блокировки на уровне БД, а не только проверки в PHP-коде.

---

## Асинхронная обработка

HTTP цикл "запрос-ответ" (request-response cycle) отделён от фактического выполнения денежной операции.

При создании операции сначала сохраняется транзакция в состоянии "В ожидании":

```text
Pending
```

После этого отправляется (dispatch):

```text
ProcessTransactionJob
```

в Redis Queue.

### HTTP flow

```text
POST /api/v1/transactions/transfer
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
Completed
        ↓
Write outbox
        ↓
Write audit event
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
Pending
   ↓
Processing
   ↓
Failed
```

Такой подход уменьшает объём тяжёлой работы внутри HTTP-запроса и позволяет независимо обрабатывать финансовые операции worker-процессами.

---

## Бухгалтерский учёт (Ledger) и аудит

### Неизменяемость бух. учёт (Immutable Ledger)

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
* баланс после операции.

Для перевода создаются две записи:

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

### Аудит (Audit Log)

Отдельный append-only `AuditLog` используется для фиксации действий приложения и пользователей.

В audit могут записываться:

* события аутентификации;
* создание счетов;
* результаты обработки транзакций;
* административные действия;
* ID запроса.

Audit-записи также не должны изменяться или удаляться через модель после создания.

---

## Аутентификация и авторизация

REST API использует Laravel Sanctum.

Поддерживаются:

```text
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/auth/me
POST /api/v1/auth/logout
```

После успешной аутентификации клиент работает с API через Bearer token.

Доступ к бизнес-ресурсам контролируется через Laravel Policies.

Клиент может работать только с доступными ему ресурсами, тогда как backoffice имеет расширенные права.

---

## REST API

### Аутентификации (Authentication)

```text
POST   /api/v1/auth/register
POST   /api/v1/auth/login
GET    /api/v1/auth/me
POST   /api/v1/auth/logout
```
Legacy `POST /api/auth/*` по-прежнему работает, но deprecated (см. [API versioning](./docs/api-versioning.md)).

### Клиенты (Customers)

```text
GET    /api/v1/customers
POST   /api/v1/customers
GET    /api/v1/customers/{uuid}
```

### Счета (Accounts)

```text
GET    /api/v1/accounts
POST   /api/v1/accounts
GET    /api/v1/accounts/{uuid}
GET    /api/v1/accounts/{uuid}/balance
GET    /api/v1/accounts/{uuid}/ledger
```

### Транзакции (Transactions)

```text
GET    /api/v1/transactions
POST   /api/v1/transactions/deposit
POST   /api/v1/transactions/withdraw
POST   /api/v1/transactions/transfer
POST   /api/v1/transactions/{uuid}/retry
GET    /api/v1/transactions/{uuid}
```

Полный API-контракт описан в OpenAPI specification:

[ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml)

---

### Пример зпроса (Example Request)

Получение Bearer token:

```text
POST /api/v1/auth/login
```

После авторизации можно выполнить денежную операцию.

Пример пополнение (deposit):

```bash
curl -X POST http://localhost/api/v1/transactions/deposit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -H "Idempotency-Key: deposit-demo-001" \
  -d '{
    "target_account_uuid": "<uuid>",
    "amount": 100000,
    "currency": "RUB"
  }'
```

### API версионирование (versioning)

Стабильный контракт: **`/api/v1/*`**.

Legacy **`/api/*`** (без `v1`) — временные aliases с заголовками deprecation (`Deprecation`, `Sunset`, `Link`).

Подробнее: [docs/api-versioning.md](./docs/api-versioning.md).

---

## Ошибки API и наблюдаемость

API использует структуру ошибок в стиле **Problem Details**.

Для корреляции HTTP-запросов используется:

```http
X-Request-Id
```

Request ID:

* принимается от клиента или генерируется приложением;
* возвращается клиенту в HTTP response;
* используется в логировании;
* связывается с аудит событиями (audit events).

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

## Тестирование и качество кода

### Feature Tests

Проект содержит Feature Tests для проверки основных контрактов системы.

Проверяются, в частности:

**Безопасность и доступ:**

* аутентификация (authentication);
* авторизация (authorization).

**Основные сущности:**

* счета (accounts);
* клиенты (customers).

**Транзакции и целостность данных:**

* создание транзакций (transaction creation);
* валидация (validation);
* идемпотентность (idempotency);
* неизменяемость реестра операций (ledger immutability).

**Асинхронная обработка:**

* асинхронная обработка (asynchronous processing).

**Аудит и трассировка:**

* журнал аудита (audit log);
* идентификатор запроса (`X-Request-Id`).

**API:**

* обработка ошибок API (API error handling);
* документация API (API documentation).

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
composer quality       # pint (fix dirty) + stan + rector + test --coverage
composer ci            # pint --test + stan + rector + test (CI gate)
make ci                # Sail: lint + test + frontend build
```

Подробнее: [docs/quality.md](./docs/quality.md).

---

## Непрерывная интеграция (CI)

GitHub Actions запускается для push и pull request в `develop`, `master`, `feature/**`.

Pipeline включает:

```text
Composer + npm ci
        ↓
Laravel (.env, key, migrate --force)
        ↓
Frontend build (npm run build)
        ↓
Laravel Pint (--test)
        ↓
PHPStan
        ↓
Rector dry-run
        ↓
PHPUnit / Feature Tests
```

Таким образом изменения автоматически проверяются на:

* корректность тестов;
* статический анализ;
* code style;
* соответствие refactoring rules;
* возможность сборки frontend.

---

## Локальная разработка

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
./vendor/bin/sail artisan migrate:fresh --seed

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

Для outbox и сверки нужен scheduler:

```bash
./vendor/bin/sail artisan schedule:work
```

Ручной запуск сверки балансов:

```bash
./vendor/bin/sail artisan reconciliation:run
./vendor/bin/sail artisan idempotency:prune-expired
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

## Демо-пользователи

Бэк-офис:

```text
admin@ledgerpay.test
StrongPassword123!
```

Клиенты:

```text
alice@ledgerpay.test
StrongPassword123!

bob@ledgerpay.test
StrongPassword123!

finance@acme.test
StrongPassword123!
```

Заблокированный demo-клиент:

```text
blocked@ledgerpay.test
StrongPassword123!
```

## Демо-данные

После:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

В приложении будут:

- реалистичные пользователи-клиенты;
- активные и заблокированные учётные записи;
- завершённые депозиты, выводы и переводы;
- пример неудачной транзакции;
- записи в бухгалтерской книге (ledger);
- журнал аудита;
- outbox-сообщения;
- отчёты сверки;

---

## Состояние и готовность

**Публичные запросы**

```text
GET /api/v1/health/live
GET /api/v1/health/ready
```

**Страница диагностики бэк-офиса:**

```text
/backoffice/diagnostics
```

**CLI:**

```bash
./vendor/bin/sail artisan diagnostics:run
```

**Проверки готовности:** PostgreSQL, Redis, backlog очереди, backlog outbox, неуспешные транзакции.

**Примечание:** Laravel `GET /up` — минимальная жизнеспособность процесса; `/api/v1/health/ready` — полная готовность LedgerPay.

После `migrate:fresh --seed` aggregate status часто **`warning`** из‑за demo failed-транзакции — это ожидаемо.

---

## Инженерные решения

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

### Инварианты на уровне базы данных (Database-level invariants)

Критичные финансовые инварианты обеспечиваются непосредственно на уровне PostgreSQL:

* неотрицательный баланс счёта (non-negative account balances);
* положительная сумма транзакции (positive transaction amounts);
* корректная структура счетов транзакции для пополнения, списания и перевода (valid transaction account shape for deposit / withdrawal / transfer);
* неизменяемость записей реестра операций (immutable ledger entries) с помощью триггеров базы данных (DB triggers);
* неизменяемость журнала аудита (immutable audit logs) с помощью триггеров базы данных (DB triggers);
* частичные индексы (partial indexes) для мониторинга транзакций со статусами `failed` и `pending`.

Это обеспечивает дополнительный уровень защиты системы даже в случае обхода валидации на уровне приложения (application-level validation).


Подробнее: [ADR-005](./docs/architecture/adr-005-database-hardening.md).

### Шаблон Outbox (Outbox pattern)

LedgerPay записывает доменные события в `outbox_messages` в той же DB-транзакции, что и бизнес-изменение.

События транзакции:

- `transaction.created` — операция заведена (Pending);
- `transaction.completed` — деньги успешно обработаны;
- `transaction.retried` — повторная постановка Failed → Pending (ручной retry);
- `transaction.failed` — терминальный сбой после retry.

Отдельная команда отправляет (dispatch) pending-сообщения в queue workers.

Это предотвращает классическую проблему: DB commit успешен, а публикация события — нет.

Подробнее: [ADR-006](./docs/architecture/adr-006-outbox-pattern.md).

### Типизированные доменные события

Изменения жизненного цикла транзакции представлены типизированными доменными событиями
и сохраняются через преобразователь outbox.

Это устраняет разрозненные массивы событий и упрощает эволюцию контрактов.

Подробнее: [ADR-009](./docs/architecture/adr-009-typed-domain-events.md).

### Kafka / Redpanda (local dev)

При `KAFKA_ENABLED=true` outbox-события дополнительно публикуются в Kafka topic.

**Broker + UI:**

```bash
make kafka-up
make kafka-topic
```

**Redpanda Console:** http://localhost:8081

**Smoke test:**

```bash
make worker
make schedule
# deposit через API
make kafka-consume   # или Console → Topics → ledgerpay.domain-events
```

CI и PHPUnit используют `KAFKA_ENABLED=false`.

### Сверка балансов

LedgerPay периодически сравнивает сохранённые балансы счетов с балансами, восстановленными из неизменяемых проводок реестра.

Это помогает обнаружить порчу данных, операционные ошибки или неожиданные мутации баланса.

Подробнее: [ADR-007](./docs/architecture/adr-007-reconciliation.md).

### Защита от злоупотреблений

LedgerPay использует ограничения скорости для конкретных запросов:

- запросы аутентификации;
- запросы глобального API;
- запросы движения средств;
- тяжелые операции в бэк-офисе.

Заголовки безопасности применяются к API и web responses.

Ключи идемпотентности имеют явное хранение метаданных и могут быть очищены после истечения.

Подробнее: [ADR-008](./docs/architecture/adr-008-rate-limiting-abuse-protection.md).

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

## Возможные следующие шаги

Возможные направления развития проекта:

- **Scoped idempotency per customer** — отдельная область `Idempotency-Key` для каждого клиента.
- **Sanctum token abilities** — разграничение прав доступа для API-токенов.
- **Query/read services** — отдельный слой для сложных выборок и отчётности.
- **Metrics** — сбор технических и бизнес-метрик системы.
- **Distributed tracing** — трассировка запросов через API, очередь и worker.
- **Load tests** — проверка системы под высокой нагрузкой.
- **Concurrency tests** — проверка корректности конкурентных операций и блокировок.
- публичный маршрут показа — [сценарий для собеседования](./docs/interview/walkthrough.md).

---

## Ссылки на документы

| Документ | Ссылка |
| -------- | ------ |
| Индекс документации | [docs/index.md](./docs/index.md) |
| Журнал изменений | [CHANGELOG.md](./CHANGELOG.md) |
| Политика безопасности | [SECURITY.md](./SECURITY.md) |
| Сценарий для собеседования | [walkthrough.md](./docs/interview/walkthrough.md) |
| Чеклист релиза | [release-checklist.md](./docs/release/release-checklist.md) |
| Swagger UI | http://localhost/api/docs |
| OpenAPI | [ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml) |
| Swagger (как устроен) | [swagger.md](./docs/architecture/swagger.md) |
| ADR (оглавление) | [docs/architecture/README.md](./docs/architecture/README.md) |
| Контекстная диаграмма | [context.md](./docs/architecture/context.md) |
| Качество кода | [quality.md](./docs/quality.md) |
| EXPLAIN / планы запросов | [query-plan-inspector.md](./docs/database/query-plan-inspector.md) |
| Подробная архитектура | [README_ARCHITECTURE.md](./README_ARCHITECTURE.md) |

---

## Цель проекта

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
