# LedgerPay Platform — архитектура

Документ описывает, **как устроен проект**, **какие архитектурные решения приняты** и **почему** они выбраны именно так. Это не API-справка и не onboarding по Laravel — здесь фокус на доменной модели, слоях и инвариантах финансовой системы.

## Связанная документация

| Документ | Описание |
|----------|----------|
| [README.md](./README.md) | Точка входа: setup, Swagger, curl-примеры |
| [Swagger UI](http://localhost/api/docs) | Интерактивная документация API (нужен запущенный Sail) |
| [ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml) | OpenAPI 3.0 spec (исходник контракта) |
| [docs/architecture/README.md](./docs/architecture/README.md) | ADR-001..004 и оглавление |
| [context.md](./docs/architecture/context.md) | Контекстная диаграмма |
| [swagger.md](./docs/architecture/swagger.md) | Как устроены OpenAPI и Swagger UI |

> Углублённое описание архитектуры. Для быстрого старта — [README.md](./README.md).

---

## Содержание

1. [Что это за система](#1-что-это-за-система)
2. [Стек и инфраструктура](#2-стек-и-инфраструктура)
3. [Архитектурный стиль](#3-архитектурный-стиль)
4. [Структура каталогов](#4-структура-каталогов)
5. [Доменная модель](#5-доменная-модель)
6. [Поток обработки денег](#6-поток-обработки-денег)
7. [Идемпотентность и конкурентность](#7-идемпотентность-и-конкурентность)
8. [Immutable Ledger и Audit Log](#8-immutable-ledger-и-audit-log)
9. [HTTP-слой и API-контракты](#9-http-слой-и-api-контракты)
10. [Тестирование](#10-тестирование)
11. [Качество кода](#11-качество-кода)
12. [Осознанные компромиссы](#12-осознанные-компромиссы)
13. [Куда развивать дальше](#13-куда-развивать-дальше)

---

## 1. Что это за система

**LedgerPay** — платформа учёта денежных операций: клиенты, счета, транзакции (ввод/вывод/переводы средств) и append-only бухгалтерский журнал (ledger).

Ключевые требования, которые определяют архитектуру:

| Требование | Как отражено в коде                                                |
|---|--------------------------------------------------------------------|
| Деньги нельзя «потерять» или зачислить дважды | DB-транзакции, блокировка строк, проверка статуса, идемпотентность |
| История операций должна быть аудируемой | `ledger_entries` — неизменяемые записи с `balance_after`           |
| Клиент может безопасно повторять запросы | `Idempotency-Key` + уникальный индекс в БД                         |
| Обработка может быть асинхронной | `ProcessTransactionJob` + очередь `transactions`                   |
| Внешний API не должен светить внутренние ID | Публичные `uuid`, numeric `id` только внутри БД                    |
| Операционный audit trail | `audit_logs` + `AuditLogger`, неизменяемый `AuditLog`                 |

---

## 2. Стек и инфраструктура

| Компонент | Выбор | Зачем                                             |
|---|---|---------------------------------------------------|
| PHP 8.5+ | strict types, enums, readonly | Типобезопасность в финансовом коде                |
| Laravel 13 | routing, Eloquent, queues, validation | Быстрая инфраструктура без изобретения велосипеда |
| PostgreSQL | через Eloquent / Sail | ACID-транзакции, атомарность движения денег     |
| Redis + Laravel Queue | `ProcessTransactionJob` | Отделение HTTP-ответа от тяжёлой обработки        |
| Laravel Sanctum | API tokens | Аутентификация REST API                         |
| Inertia + Vue 3 + Tailwind | web/backoffice UI | Клиентский кабинет и операторский backoffice     |
| OpenAPI + Swagger UI | `docs/openapi/`, `/api/docs` | Контракт API и интерактивная документация       |
| PHPUnit | feature-тесты | Проверка контрактов движения денег     |
| PHPStan + Pint + Rector | `composer quality` | Статический анализ и единый стиль                 |
| GitHub Actions | `.github/workflows/ci.yml` | `quality:ci`, frontend build, PostgreSQL на push/PR |

Локальная разработка: **`./vendor/bin/sail up -d`** (PostgreSQL, Redis, app) — основной путь в README. Альтернатива **`composer dev`** (serve + queue + pail + Vite) — без Sail, нужны локальные PostgreSQL и Redis.

---

## 3. Архитектурный стиль

Проект следует **прагматичному DDD на Laravel**, а не «чистому» DDD с отдельными репозиториями, aggregate roots и event sourcing.

### Три слоя

```
┌─────────────────────────────────────────────────────────┐
│  HTTP (Controllers, Requests, Resources)                │
│  — валидация входа, сериализация ответа, без логики     │
└───────────────────────────┬─────────────────────────────┘
                            │ DTO
┌───────────────────────────▼─────────────────────────────┐
│  Application (Services, Jobs, DTO, Results)             │
│  — orchestration use cases, очереди, координация        │
└───────────────────────────┬─────────────────────────────┘
                            │ вызовы доменных методов
┌───────────────────────────▼─────────────────────────────┐
│  Domain (Models, Enums, Exceptions, Domain Services)    │
│  — инварианты, бизнес-правила, состояние агрегатов      │
└─────────────────────────────────────────────────────────┘
```

### Почему именно так

**Eloquent-модели в Domain, а не в Infrastructure.**  
Laravel — это и ORM, и runtime. Вынос моделей в «инфраструктурный» слой с маппингом Domain ↔ DB добавил бы шаблонный код без выигрыша для текущего масштаба. Доменные инварианты (`Account::debit()`, immutability ledger) живут прямо в моделях — это осознанный trade-off: **простота > пуристский DDD**.

**Application Services вместо Fat Controllers.**  
Контроллеры (`TransactionController`) только мапят HTTP → DTO → Service → Resource. Сценарии «создать pending-транзакцию и поставить job» и «обработать деньги атомарно» разделены на `TransactionService` и `TransactionProcessorService` — это два разных use case с разной семантикой retry и идемпотентности.

**Domain Services только для правил, не привязанных к одной модели.**  
`TransferPolicy::assertDifferentAccounts()` — пример: правило перевода затрагивает пару счетов, а не один агрегат.

**Infrastructure = Laravel primitives.**  
Jobs, migrations, factories, HTTP — это инфраструктура фреймворка. Отдельный namespace `Infrastructure/` не заводился: нет смысла дублировать то, что Laravel уже даёт из коробки.

---

## 4. Структура каталогов

```
app/
├── Domain/
│   ├── Account/          # Account, AccountStatus, исключения баланса/валюты
│   ├── Audit/            # AuditLog, AuditAction (append-only операционный журнал)
│   ├── Customer/         # Customer, CustomerStatus
│   ├── Ledger/           # LedgerEntry, LedgerDirection
│   ├── Shared/           # IDomainRuleViolation — маркер доменных ошибок для API
│   └── Transaction/      # Transaction, TransactionType/Status, TransferPolicy
├── Application/
│   ├── Account/          # AccountService, CreateAccountData
│   ├── Audit/            # AuditLogger — запись audit-событий
│   ├── Auth/             # AuthService, RegisterCustomerUserData, LoginData
│   ├── Customer/         # CustomerService
│   ├── Ledger/           # LedgerService (создание проводок)
│   └── Transaction/      # TransactionService, TransactionProcessorService,
│                         # ProcessTransactionJob, DTO, TransactionCreationResult
├── Policies/             # AccountPolicy, TransactionPolicy, CustomerPolicy
├── Support/Http/         # ProblemDetails (RFC 7807)
└── Http/
    ├── Controllers/
    │   ├── Api/          # REST-контроллеры (Sanctum)
    │   └── Web/          # Inertia UI: dashboard, backoffice
    ├── Middleware/       # RequestIdMiddleware, ApiRequestLoggingMiddleware, EnsureBackofficeUser, …
    ├── Requests/         # Form Request validation
    └── Resources/        # JSON-сериализация (uuid, не id)
```

**Правило зависимостей:** Domain не знает про HTTP и Jobs. Application знает про Domain и Laravel (DB, Queue). HTTP знает про Application и Domain (read-запросы вроде `index`/`show`). Authorization — Laravel Policies в `app/Policies/`, вызываются из контроллеров через `$this->authorize()`.

---

## 5. Доменная модель

### Агрегаты и связи

```
Customer (1) ──< Account (N)
                      │
                      ├── balance (integer, minor units)
                      └── ledger_entries (N)

Transaction (1) ──< LedgerEntry (N)
     │
     ├── source_account (nullable)
     └── target_account (nullable)
```

### Customer

- Владелец счетов.
- Статус `Active` / иные — операции по счетам inactive-клиента блокируются на уровне `AccountService`.

### Account

- Баланс хранится в **minor units** (копейки, центы): `100.50 USD → 10050`.
- **Почему integer, а не decimal/float:** float даёт ошибки округления; decimal усложняет код; integer — стандарт для payment systems.
- Методы `credit()` / `debit()` инкапсулируют инварианты:
  - счёт активен;
  - валюта операции совпадает с валютой счёта;
  - при debit — достаточно средств.
- Исключения домена: `InsufficientFundsException`, `InactiveAccountException`, `CurrencyMismatchException`.

### Transaction

- Описывает **намерение** движения денег (deposit / withdrawal / transfer).
- Жизненный цикл через `TransactionStatus`:

  ```
  Pending → Processing → Completed
                      ↘ Failed → (retry) → Pending → ...
  ```

- `Cancelled` — зарезервирован в enum, в MVP не выставляется бизнес-логикой.
- `Pending` создаётся синхронно в HTTP; `Completed` — только после processor + ledger.
- Публичный идентификатор — `uuid`; `id` — внутренний FK.

### LedgerEntry

- Append-only запись факта движения (Debit / Credit).
- Хранит `balance_after` — снимок баланса **после** операции для аудита и расследований.
- Immutability enforced в модели (см. [раздел 8](#8-immutable-ledger-и-audit-log)).

---

## 6. Поток обработки денег

Обработка **намеренно разделена на два этапа**: быстрый HTTP-ответ и асинхронное движение денег.

### Этап 1 — HTTP: создание намерения (sync)

```
Client POST /api/transactions/deposit
    │
    ▼
DepositRequest (валидация + Idempotency-Key)
    │
    ▼
TransactionController → TransactionService::deposit()
    │
    ├── createTransactionOnce()  → Transaction (Pending)
    └── dispatchIfNewPending()   → ProcessTransactionJob (только если created=true)
    │
    ▼
HTTP 201 Created (новая транзакция, status: pending)
    или
HTTP 200 OK (idempotent replay — тот же uuid, без повторного dispatch)
```

Контракт **201 / 200** зафиксирован в [OpenAPI](./docs/openapi/ledgerpay.openapi.yaml) и feature-тестах (`TransactionApiTest`, `TransactionProcessingTest`).

**Почему async:**  
HTTP не должен ждать блокировок счетов, retry доменных ошибок и записи в ledger. Клиент получает подтверждение «запрос принят» и может запросом смотреть статус по `uuid`.

**Почему два сервиса (`TransactionService` vs `TransactionProcessorService`):**

| | TransactionService | TransactionProcessorService                           |
|---|---|-------------------------------------------------------|
| Когда | HTTP-request | Queue worker                                          |
| Что делает | Создаёт Pending, dispatch job | Двигает баланс, пишет ledger, ставит Completed/Failed |
| Идемпотентность | По `idempotency_key` | По `status` + блокировке строки                       |
| Retry | Не retry'ит сам | Job retry ×5, ручной retry через API                  |

### Этап 2 — Worker: исполнение (async)

```
ProcessTransactionJob
    │
    ├── WithoutOverlapping (queue-level dedup)
    ├── skip if Completed / Failed
    │
    ▼
TransactionProcessorService::process()
    │
    ├── DB::transaction
    ├── lockTransaction (SELECT ... FOR UPDATE)
    ├── skip if Completed (defense in depth)
    ├── status → Processing
    ├── lockAccount(s) — порядок по id (anti-deadlock для transfer)
    ├── Account::debit/credit + save
    ├── LedgerService::debit/credit
    ├── status → Completed, processed_at
    └── AuditLogger::log(TransactionCompleted | TransactionFailed)
        (без HTTP context в worker — actor_user_id = null)
```

### Transfer — особый случай

Перевод блокирует **оба** счёта в стабильном порядке (`ORDER BY id`) — защита от deadlock при встречных переводах A→B и B→A.  
`TransferPolicy` проверяет, что source ≠ target.

---

## 7. Идемпотентность и конкурентность

Идемпотентность — **многослойная**, но каждый слой закрывает свой класс проблем. Это не дублирование «на всякий случай».

### Слой 1 — API: Idempotency-Key

- Обязательный заголовок `Idempotency-Key` на deposit/withdraw/transfer.
- Уникальный индекс на `transactions.idempotency_key`.
- Повторный POST с тем же ключом → тот же `uuid`, **HTTP 200** (без второй транзакции и без повторного dispatch job).

**Почему global unique, а не scoped по client:**  
Проще для MVP. В миграции есть комментарий, что позже можно усилить scoped idempotency (по клиенту / endpoint).

### Слой 2 — TransactionService::createTransactionOnce()

```php
// 1. Fast path — без лишней DB-транзакции
$existing = findByIdempotencyKey($key);

// 2. Внутри DB::transaction — lockForUpdate + повторная проверка
// 3. Unique index — финальная защита при concurrent create
```

| Механизм | Роль                                                                        |
|---|-----------------------------------------------------------------------------|
| `findByIdempotencyKey` | Оптимизация для типичного retry                                             |
| `lockForUpdate` внутри транзакции | Если запись уже есть — второй запрос дождётся                               |
| Unique index | Условия конкуренции при одновременном создании двух запросов с одним ключом |

### Слой 3 — dispatchIfNewPending()

`TransactionCreationResult` с флагом `created` решает проблему **повторного dispatch job** при idempotency-retry.  
Без этого повторный HTTP-запрос с тем же ключом снова ставил бы `ProcessTransactionJob`, пока status = Pending.

### Слой 4 — ProcessTransactionJob

- `WithoutOverlapping('transaction:{id}')` — снижает параллельную обработку одной транзакции на уровне очереди.
- Early return для `Completed` / `Failed` — простая предварительная проверка перед обработчиком.

**WithoutOverlapping — вспомогательный**, не главный: authoritative lock — `lockForUpdate` в processor.

### Слой 5 — TransactionProcessorService

- `lockTransaction()` + проверка `Completed` внутри DB-транзакции.
- `lockAccount()` — консистентность баланса при конкурентных операциях по одному счёту.

### Retry failed-транзакций

`POST /api/transactions/{uuid}/retry` — явный use case:

1. Только для `Failed`.
2. Сброс в `Pending`, очистка `failure_reason`.
3. Новый dispatch job.

**Почему не автоматический бесконечный retry:**  
Доменные ошибки (недостаточно средств, неактивный клиент) не исчезнут сами — нужен оператор или изменение условий.

---

## 8. Immutable Ledger и Audit Log

### LedgerEntry — финансовый журнал

`LedgerEntry` — **append-only** запись движения денег по счёту.

После создания запись **нельзя** update/delete — enforced в `booted()` модели:

```php
self::updating(fn () => throw new LogicException(...));
self::deleting(fn () => throw new LogicException(...));
```

**Почему на уровне модели, а не только Policy/Service:**

- Инвариант финансового журнала должен быть невозможно обойти случайным `$entry->update()`.
- Тесты (`LedgerImmutabilityTest`) фиксируют контракт.

### AuditLog — операционный журнал

`AuditLog` — **append-only** запись действий пользователей и системы (login, создание счёта, завершение/ошибка транзакции и т.д.).

Тот же инвариант immutability в `booted()`:

```php
self::updating(fn () => throw new LogicException(...));
self::deleting(fn () => throw new LogicException(...));
```

`AuditLogger` (Application) пишет события из **Web**-контроллеров (Inertia: login, счета, web-транзакции, backoffice views — с `actor_user_id`, `X-Request-Id`) и из `ProcessTransactionJob` (без HTTP context). **REST API** (`Api/*`) audit не пишет на create-транзакции — только worker (`TransactionCompleted` / `TransactionFailed`). Просмотр — в backoffice `/backoffice/audit-logs`.

**Исправление ошибок** — только через новые корректирующие транзакции, не правку старых проводок и audit-записей.

`LedgerService` отделён от `TransactionProcessorService`:  
он **только создаёт** записи, не меняет баланс и не содержит бизнес-логики обработки.

---

## 9. HTTP-слой и API-контракты

### Controllers — thin

Контроллеры не содержат бизнес-логики. Паттерн:

```
Request → DTO → Application Service → Resource
```

### DTO (Application layer)

`CreateDepositData`, `CreateWithdrawalData`, `CreateTransferData` — граница между HTTP-форматом и use case.  
**Почему не массивы:** typed DTO дают контракт для сервиса и PHPStan.

### Form Requests

Валидация формата + обязательность `Idempotency-Key`.  
Trim пробелов в ключе — защита от «разных» ключей с тем же смыслом.

### API Resources

JSON отдаёт **uuid**, не numeric `id`.  
Amount — integer (minor units).  
Status/type — string enum values.  
Списки и create-ответы оборачиваются в `{ "data": ... }` (Laravel API Resources). **`GET /api/transactions/{uuid}`** (`show`) — исключение: плоский JSON без обёртки `data` (через `TransactionResource::resolve()`).

### Аутентификация и авторизация

- **Sanctum** — bearer token на всех business routes (`auth:sanctum`).
- **Auth API:** `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/logout`.
- **Policies** (`AccountPolicy`, `TransactionPolicy`, `CustomerPolicy`) — customer (клиент) видит только свои счета/транзакции; backoffice user — все.
- Контроллеры вызывают `$this->authorize()` до write и на read по uuid.

### Ошибки и observability

- **RFC 7807 Problem Details** — ошибки API в `application/problem+json` (`ProblemDetails`, `bootstrap/app.php`).
- **X-Request-Id** — correlation id через `RequestIdMiddleware` (генерируется или пробрасывается клиентом); попадает в логи и `audit_logs.request_id`.
- **Structured API logging** — `ApiRequestLoggingMiddleware` на API stack (request/response metadata с `request_id`).
- Доменные нарушения (`IDomainRuleViolation`) → **409**; validation → **422**; auth → **401/403**.

### Read vs Write asymmetry

| Операция | Где логика |
|---|---|
| deposit/withdraw/transfer | Application Service |
| index/show/balance/ledger | Прямые Eloquent-запросы в Controller |

**Почему read без сервиса:**  
Read-модели простые, без инвариантов. При росте сложности (фильтры, авторизация, CQRS) read вынесется в Query Services.

### Маршруты API (Sanctum)

```
# Auth (часть без token, часть с auth:sanctum)
POST   /api/auth/register
POST   /api/auth/login
GET    /api/auth/me
POST   /api/auth/logout

# Customers (backoffice)
GET    /api/customers
POST   /api/customers
GET    /api/customers/{uuid}

# Accounts
GET    /api/accounts
POST   /api/accounts
GET    /api/accounts/{uuid}
GET    /api/accounts/{uuid}/balance
GET    /api/accounts/{uuid}/ledger

# Transactions (Idempotency-Key на deposit/withdraw/transfer)
GET    /api/transactions
POST   /api/transactions/deposit
POST   /api/transactions/withdraw
POST   /api/transactions/transfer
POST   /api/transactions/{uuid}/retry
GET    /api/transactions/{uuid}
```

Полный контракт API — [ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml) · интерактивно: [Swagger UI](http://localhost/api/docs) (local, Sail).

### Web UI (Inertia)

Параллельный **session-based** слой для браузера (не token API):

- Клиент: `/`, `/accounts/{uuid}/ledger`, POST-транзакции через web-контроллеры.
- Backoffice (`middleware backoffice`): `/backoffice`, `/backoffice/customers/{uuid}`, `/backoffice/transactions`, `/backoffice/audit-logs`.

Те же доменные сервисы, другой transport (Inertia props вместо JSON Resources).

---

## 10. Тестирование

Feature-тесты сгруппированы по контрактам:

| Группа | Файлы | Что проверяет |
|--------|-------|---------------|
| **Transactions (HTTP + async)** | `Api/TransactionApiTest`, `TransactionProcessingTest`, `LedgerImmutabilityTest` | Validation, idempotency (201/200), error responses; `Queue::fake()` + `job->handle()`; append-only ledger |
| **API auth & CRUD** | `Api/AuthApiTest`, `Api/AccountApiTest`, `Api/CustomerApiTest` | Register/login, accounts, customers |
| **Authorization** | `Api/AccountAuthorizationTest`, `BackofficeAccessTest` | Policies: customer vs backoffice |
| **Errors & correlation** | `Api/ApiErrorHandlingTest` | Problem Details, `X-Request-Id` |
| **Audit** | `AuditLogTest` | Immutable audit + события из HTTP/worker |
| **Docs** | `ApiDocsTest` | Swagger UI и OpenAPI spec (local guards) |

**Почему `Queue::fake()` в orchestration-тестах:**  
Изолирует «создание + dispatch» от «движение денег». Processor тестируется отдельно через прямой вызов `handle()`.

| Файл (ядро транзакций) | Фокус |
|---|---|
| `TransactionApiTest` | End-to-end HTTP: validation, idempotency, error responses |
| `TransactionProcessingTest` | Async-контракт: `Queue::fake()` + ручной `job->handle()` |
| `LedgerImmutabilityTest` | Доменный инвариант append-only ledger |

Factories (`AccountFactory`, `CustomerFactory`, `TransactionFactory`) живут в `database/factories/`, но модели Domain явно указывают `newFactory()` — Laravel не резолвит factory автоматически для namespace `App\Domain\...`.

---

## 11. Качество кода

```bash
composer quality     # pint:test + stan + rector:test
composer ci          # + test:ci (CI gate)
make ci              # + npm run build
composer pint        # fix dirty files
composer stan        # PHPStan level 6
composer rector      # apply refactoring
```

Подробнее: [docs/quality.md](./docs/quality.md).

- `declare(strict_types=1)` — везде.
- `final` на сервисах и контроллерах — явный запрет на неожиданное наследование.
- `readonly` на application services — immutability зависимостей через constructor injection.

### CI

GitHub Actions ([`.github/workflows/ci.yml`](./.github/workflows/ci.yml)): на push/PR в `develop`, `master`, `feature/**` — PostgreSQL 18, явные steps Pint / PHPStan / Rector / Tests, `npm run build`, `php artisan migrate --force`.

---

## 12. Осознанные компромиссы

Что **не** сделано, что это означает и чем компенсируется:

| Не сделано | Что это / зачем | Почему не в проекте |
|------------|-----------------|---------------------|
| **Event Sourcing** | История хранится как поток событий, состояние — их проекция; удобно для аудита и replay | Избыточно на текущем масштабе; `ledger_entries` + `audit_logs` уже дают audit trail |
| **Repository interfaces** | Абстракция доступа к БД поверх ORM; «чистый» DDD | Eloquent достаточно; меньше boilerplate, модели уже в Domain |
| **CQRS** | Разные модели для записи и чтения (command vs query) | Read-сценарии простые (списки, выписки); усложнение не окупается |
| **Saga / Outbox** | Outbox — надёжная доставка событий вовне (`transaction.created`, `transaction.completed`, `transaction.failed`, `transaction.retried`); Saga — распределённые транзакции между сервисами | Outbox реализован; типизированные события — ADR-009; Saga — при multi-service |
| **Scoped idempotency** | Ключ идемпотентности уникален в рамках клиента, а не глобально | Сейчас global unique — проще для MVP |
| **Классический double-entry (дебет = кредит всегда)** | Каждая операция — парные проводки на план счетов | Deposit/withdraw — односторонние проводки; transfer — debit + credit; достаточно для demo |

Что **можно упростить** без потери корректности (если нужен минимализм):

- убрать pre-check `findByIdempotencyKey` (оставить transaction + unique index);
- убрать early return `Completed` в job (оставить только в processor);
- убрать `WithoutOverlapping` (полагаться только на DB locks).

Но **убирать нельзя**: unique index, `dispatchIfNewPending`, `lockForUpdate` в processor.

---

## 13. Куда развивать дальше

Естественные следующие шаги без ломки текущей архитектуры:

1. **Scoped idempotency** — `(customer_id, idempotency_key)` unique вместо global; ключ уникален в рамках клиента, а не всей системы — разные клиенты могут использовать одинаковые ключи без конфликта.
2. **Rate limiting и scoped tokens** — расширить throttling на API auth и money movement (web login уже ограничен в `LoginRequest`); Sanctum abilities per scope — минимальные права токена.
3. **Outbox + типизированные доменные события** — реализован (ADR-006, ADR-009): `transaction.created` / `transaction.completed` / `transaction.failed` / `transaction.retried`; Kafka transport — Redpanda при `KAFKA_ENABLED=true`.
4. **Read Services** — при усложнении выписок и отчётов; сложное чтение выносится из контроллеров в отдельные сервисы — проще оптимизировать SQL и не раздувать HTTP-слой.
5. **Observability** — Telescope в dev; добавить **OpenAPI lint** в CI (`quality:ci` и `npm run build` уже в [GitHub Actions](./.github/workflows/ci.yml)).

---

## Диаграмма: полный путь deposit

```
┌──────────┐  POST /api/transactions/deposit  ┌───────────────────────┐
│  Client  │ ────────────────────────────────►│ TransactionController │
└──────────┘   Idempotency-Key + Bearer token └────────┬──────────────┘
                                                       │
                                                       ▼
                                            ┌───────────────────────┐
                                            │  TransactionService   │
                                            │  createTransactionOnce│
                                            │  dispatchIfNewPending │
                                            └──────────┬────────────┘
                                                       │
                                 ┌─────────────────────┼─────────────────────┐
                                 ▼                     ▼                     ▼
                          transactions           jobs table           HTTP 201 / 200
                          (status: pending)   ProcessTransactionJob
                                                       │
                                                       ▼
                                            ┌────────────────────────────┐
                                            │ TransactionProcessorService│
                                            │ lock → credit → ledger     │
                                            │ status: completed          │
                                            │ AuditLogger (completed)    │
                                            └────────────────────────────┘
                                                       │
                                 ┌─────────────────────┼─────────────────────┐
                                 ▼                     ▼                     ▼
                          accounts.balance+     ledger_entries (credit)   processed_at
```

---

*Документ отражает состояние кодовой базы на ветке `develop`. См. также [ADR](./docs/architecture/README.md) и [OpenAPI spec](./docs/openapi/ledgerpay.openapi.yaml).*
