# LedgerPay Platform — архитектура

Документ описывает, **как устроен проект**, **какие архитектурные решения приняты** и **почему** они выбраны именно так. Это не API-справка и не адаптация с помощью Laravel — здесь фокус на доменной модели, слоях и инвариантах финансовой системы.

## Связанная документация

| Документ | Описание |
|----------|----------|
| [README.md](./README.md) | Точка входа: setup, Swagger, curl-примеры |
| [docs/index.md](./docs/index.md) | Индекс документации |
| [CHANGELOG.md](./CHANGELOG.md) | Журнал изменений (portfolio release) |
| [SECURITY.md](./SECURITY.md) | Политика безопасности и "не цели проекта" |
| [Swagger UI](http://localhost/api/docs) | Интерактивная документация API (нужен запущенный Sail) |
| [ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml) | OpenAPI 3.0 spec (исходник контракта v1) |
| [docs/api-versioning.md](./docs/api-versioning.md) | Версионирование API: `/api/v1`, legacy aliases, заголовки |
| [docs/architecture/README.md](./docs/architecture/README.md) | ADR и оглавление (001–010) |
| [adr-010-health-readiness-diagnostics.md](./docs/architecture/adr-010-health-readiness-diagnostics.md) | Работоспособность/Готовность (Liveness/readiness), CLI, страница диагностики |
| [context.md](./docs/architecture/context.md) | Контекстная диаграмма |
| [swagger.md](./docs/architecture/swagger.md) | Как устроены OpenAPI и Swagger UI |
| [interview/walkthrough.md](./docs/interview/walkthrough.md) | Сценарий показа проекта на собеседовании |

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
| Журнал аудита операций | `audit_logs` + `AuditLogger`, неизменяемый `AuditLog`                 |

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

### Состояние, готовность и диагностика

Публичные эндпоинты (без Sanctum):

```text
GET /api/v1/health/live
GET /api/v1/health/ready
```

- **live** — работоспособность, процесс приложения отвечает;
- **ready** — агрегированная готовность: PostgreSQL, Redis, backlog очереди, outbox, неуспешные транзакции (при `failed` checks → HTTP 503).

Операторские инструменты:

```text
/backoffice/diagnostics
./vendor/bin/sail artisan diagnostics:run
```

Laravel `GET /up` — минимальная работоспособность (liveness) на уровне фреймворка; для мониторинга LedgerPay используйте `/api/v1/health/ready`.

Подробнее: [ADR-010](./docs/architecture/adr-010-health-readiness-diagnostics.md).

---

## 3. Архитектурный стиль

Проект следует **прагматичному DDD на Laravel**, а не «чистому» DDD с отдельными репозиториями, aggregate roots и event sourcing.

### Три слоя

```text
┌─────────────────────────────────────────────────────────┐
│  HTTP (Controllers, Requests, Resources)                │
│  — валидация входных данных, сериализация ответа,       │
│    без бизнес-логики                                    │
└───────────────────────────┬─────────────────────────────┘
                            │ DTO
┌───────────────────────────▼─────────────────────────────┐
│  Application (Services, Jobs, DTO, Results)             │
│  — оркестрация сценариев использования, очереди,        │
│    координация                                          │
└───────────────────────────┬─────────────────────────────┘
                            │ вызовы доменных методов
┌───────────────────────────▼─────────────────────────────┐
│  Domain (Models, Enums, Exceptions, Domain Services)    │
│  — инварианты, бизнес-правила, состояние агрегатов      │
└─────────────────────────────────────────────────────────┘
```

### Почему именно так

* **Eloquent-модели находятся в Domain, а не в Infrastructure.**

  Laravel — это одновременно ORM и среда выполнения приложения. Вынос моделей в отдельный инфраструктурный слой с преобразованием `Domain ↔ DB` добавил бы лишний шаблонный код без заметной пользы для текущего масштаба проекта. Доменные инварианты (`Account::debit()`, неизменяемость ledger) реализованы непосредственно в моделях. Это осознанный компромисс: **простота важнее строгого следования DDD**.

* **Application Services вместо Fat Controllers.**

  Контроллеры (`TransactionController`) отвечают только за преобразование HTTP-запроса в DTO, вызов сервиса и формирование ответа через Resource: `HTTP → DTO → Service → Resource`.

  Сценарии «создать pending-транзакцию и поставить задачу в очередь» и «атомарно обработать денежную операцию» разделены между `TransactionService` и `TransactionProcessorService`. Это два разных сценария использования с различной семантикой повторных попыток и идемпотентности.

* **Domain Services используются только для правил, не принадлежащих одной модели.**

  `TransferPolicy::assertDifferentAccounts()` — пример такого правила: оно относится сразу к двум счетам, а не к одной конкретной модели.

* **Infrastructure — это стандартные механизмы Laravel.**

  Jobs, migrations, factories и HTTP-слой уже являются частью инфраструктуры приложения, предоставляемой Laravel. Поэтому отдельный namespace `Infrastructure/` не вводится: нет необходимости дублировать архитектурными абстракциями то, что фреймворк уже предоставляет из коробки.

---

## 4. Структура каталогов

```
app/
├── Domain/
│   ├── Account/
│   ├── Audit/
│   ├── Customer/
│   ├── Ledger/
│   ├── Outbox/
│   ├── Reconciliation/
│   ├── Shared/
│   └── Transaction/      # модели, enums, Events, TransferPolicy
├── Application/
│   ├── Account/
│   ├── Audit/            # AuditLogger
│   ├── Auth/
│   ├── Customer/
│   ├── Diagnostics/      # readiness checks, DiagnosticsService
│   ├── Ledger/
│   ├── Outbox/
│   ├── Reconciliation/
│   └── Transaction/      # TransactionService, TransactionProcessorService,
│                         # ProcessTransactionJob, DTO
├── Policies/
├── Support/Http/         # ProblemDetails (application/problem+json)
└── Http/
    ├── Controllers/
    │   ├── Api/
    │   │   └── V1/       # в т.ч. HealthController (live/ready)
    │   └── Web/          # Inertia: dashboard, backoffice
    ├── Middleware/
    ├── Requests/
    └── Resources/
        └── V1/
```

Маршруты REST: **`routes/api_v1.php`** (канон, prefix `/api/v1`) + legacy aliases в **`routes/api.php`** под middleware `api.deprecated` (без route names). Имена `route('api.*')` резолвятся в **`/api/v1/...`**.

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

### Клиент (Customer)

* Владелец счетов.
* Статус клиента — `Active` или иной. Операции по счетам неактивного клиента блокируются на уровне `AccountService`.

### Счёт (Account)

* Баланс хранится в **минимальных денежных единицах** (копейки, центы): `100.50 USD → 10050`.
* **Почему integer, а не decimal/float:** `float` приводит к ошибкам округления, `decimal` усложняет работу с денежными значениями, а хранение суммы как целого числа в минимальных денежных единицах — распространённый подход в платёжных системах.
* Методы `credit()` и `debit()` инкапсулируют доменные инварианты:

    * счёт активен;
    * валюта операции совпадает с валютой счёта;
    * при списании на счёте достаточно средств.
* Доменные исключения: `InsufficientFundsException`, `InactiveAccountException`, `CurrencyMismatchException`.

### Операция (Transaction)

* Описывает **намерение провести движение денежных средств**: пополнение (`deposit`), списание (`withdrawal`) или перевод (`transfer`).

* Жизненный цикл определяется через `TransactionStatus`:

  ```text
  Pending → Processing → Completed
                      ↘ Failed → (повторная попытка) → Pending → ...
  ```

* `Cancelled` зарезервирован в enum, но в MVP не устанавливается бизнес-логикой.

* `Pending` создаётся синхронно в HTTP-слое; `Completed` устанавливается только после успешной обработки операции и записи в реестр.

* Публичный идентификатор — `uuid`; `id` используется как внутренний внешний ключ.

### Реестр операций (LedgerEntry)

* Неизменяемая запись факта движения денежных средств: `Debit` или `Credit`.
* Хранит `balance_after` — снимок баланса **после** операции, используемый для аудита и расследований.
* Неизменяемость обеспечивается на уровне модели (см. [раздел 8](#8-immutable-ledger-и-audit-log)).


---

## 6. Поток обработки денег

Обработка **намеренно разделена на два этапа**: быстрый HTTP-ответ и асинхронное выполнение денежной операции.

### Этап 1 — HTTP: создание намерения (синхронно)

```text
Client POST /api/v1/transactions/deposit
    │
    ▼
DepositRequest (валидация + Idempotency-Key)
    │
    ▼
TransactionController → TransactionService::deposit()
    │
    ├── createTransactionOnce() → Transaction (Pending)
    └── dispatchIfNewPending()  → ProcessTransactionJob
                                 (только если created=true)
    │
    ▼
HTTP 201 Created
(новая транзакция, status: pending)

    или

HTTP 200 OK
(повтор идемпотентного запроса — тот же uuid,
без повторной постановки job в очередь)
```

Контракт **201 / 200** зафиксирован в [OpenAPI](./docs/openapi/ledgerpay.openapi.yaml) и feature-тестах (`TransactionApiTest`, `TransactionProcessingTest`). Часть тестов по-прежнему использует legacy URL `/api/*`; его поведение идентично API v1.

**Почему асинхронно.**
HTTP-запрос не должен ожидать блокировок счетов, повторных попыток при ошибках и записи в реестр. Клиент сразу получает подтверждение о принятии запроса, а затем может проверять состояние транзакции по `uuid`.

**Почему используются два сервиса: `TransactionService` и `TransactionProcessorService`.**

|                   | `TransactionService`                     | `TransactionProcessorService`                                                     |
| ----------------- | ---------------------------------------- | --------------------------------------------------------------------------------- |
| Где выполняется   | HTTP-запрос                              | Обработчик очереди                                                                |
| Ответственность   | Создаёт `Pending` и ставит job в очередь | Изменяет баланс, записывает данные в ledger, устанавливает `Completed` / `Failed` |
| Идемпотентность   | По `idempotency_key`                     | По `status` и блокировке строки                                                   |
| Повторные попытки | Самостоятельно не выполняет              | До 5 попыток job + ручной retry через API                                         |


### Этап 2 — Worker: исполнение (асинхронно)

```text
ProcessTransactionJob
    │
    ├── WithoutOverlapping (защита от параллельной обработки в очереди)
    ├── пропуск, если статус Completed / Failed
    │
    ▼
TransactionProcessorService::process()
    │
    ├── DB::transaction
    ├── lockTransaction (SELECT ... FOR UPDATE)
    ├── пропуск, если статус Completed (defense in depth)
    ├── status → Processing
    ├── lockAccount(s) — блокировка по id
    │                    (защита от deadlock при transfer)
    ├── Account::debit/credit + save
    ├── LedgerService::debit/credit
    ├── status → Completed, processed_at
    └── AuditLogger::log(TransactionCompleted | TransactionFailed)
        (в worker нет HTTP-контекста, поэтому actor_user_id = null)
```

### Transfer — особый случай

Перевод блокирует **оба счёта в стабильном порядке по `id`** (`ORDER BY id`). Это снижает риск deadlock при встречных переводах `A → B` и `B → A`.

`TransferPolicy` дополнительно проверяет, что исходный и целевой счеты различаются (`source ≠ target`).

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
- Ранний возврат для `Completed` / `Failed` — простая предварительная проверка перед обработчиком.

**WithoutOverlapping — вспомогательный**, не главный: authoritative lock — `lockForUpdate` в processor.

### Слой 5 — TransactionProcessorService

- `lockTransaction()` + проверка `Completed` внутри DB-транзакции.
- `lockAccount()` — консистентность баланса при конкурентных операциях по одному счёту.

### Retry failed-транзакций

`POST /api/v1/transactions/{uuid}/retry` — явный случай использования (legacy: `POST /api/transactions/{uuid}/retry`, deprecated):

1. Только для `Failed`.
2. Сброс в `Pending` (в ожидание), очистка `failure_reason` (причины сбоя).
3. Новый dispatch job.

**Почему не автоматический бесконечный retry:**  
Доменные ошибки (недостаточно средств, неактивный клиент) не исчезнут сами — нужен оператор или изменение условий.

---

## 8. Immutable Ledger и Audit Log

### LedgerEntry — финансовый журнал, реестр

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
**Почему не массивы:** тип DTO дают контракт для сервиса и PHPStan.

### Form Requests

Валидация формата + обязательность `Idempotency-Key`.  
Trim пробелов в ключе — защита от «разных» ключей с тем же смыслом.

### API Resources

JSON отдаёт **uuid**, не numeric `id`.  
Amount — integer (minor units).  
Status/type — string enum values.  
Списки и create-ответы оборачиваются в `{ "data": ... }` (Laravel API Resources). **`GET /api/v1/transactions/{uuid}`** (`show`) — исключение: плоский JSON без обёртки `data` (через `TransactionResource::resolve()`).

Публичный контракт v1 зафиксирован namespace **`App\Http\Resources\V1\*`** (thin aliases над base resources).

### Версионирование API

| Префикс | Роль |
|---------|------|
| **`/api/v1/*`** | Стабильный публичный контракт (OpenAPI, новые интеграции) |
| **`/api/*`** (без `v1`) | Deprecated compatibility alias; middleware `api.deprecated` |

Заголовки ответа:

- **`/api/v1/*`:** `X-API-Version: v1`
- **Legacy `/api/*`:** `X-API-Version: legacy`, `Deprecation: true`, `Sunset`, `Link: </api/v1>; rel="successor-version"`

Регистрация: `Route::prefix('v1')->group(routes/api_v1.php)`; legacy — те же URI на base `Api\*` controllers. HTTP entry v1 — `Api\V1\*` (наследуют base).

Подробнее: [docs/api-versioning.md](./docs/api-versioning.md).

### Аутентификация и авторизация

- **Sanctum** — bearer token на всех business routes (`auth:sanctum`).
- **Auth API (канон):** `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`.
- Legacy `/api/auth/*` — тот же JSON-контракт, deprecated headers.
- **Policies** (`AccountPolicy`, `TransactionPolicy`, `CustomerPolicy`) — customer (клиент) видит только свои счета/транзакции; backoffice user — все.
- Контроллеры вызывают `$this->authorize()` до write и на read по uuid.

### Ошибки и observability

- **Problem Details** — ошибки API в `application/problem+json`, стиль RFC 7807 (`ProblemDetails`, `bootstrap/app.php`).
- **X-Request-Id** — correlation id через `RequestIdMiddleware` (генерируется или пробрасывается клиентом); попадает в логи и `audit_logs.request_id`.
- **Structured API logging** — `ApiRequestLoggingMiddleware` на API stack (request/response metadata с `request_id`).
- **X-API-Version** — `ApiVersionHeaderMiddleware` на api stack (`v1` vs `legacy`).
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
# Канонический контракт — /api/v1 (имена route('api.*') → v1)
POST   /api/v1/auth/register
POST   /api/v1/auth/login
GET    /api/v1/auth/me
POST   /api/v1/auth/logout

GET    /api/v1/customers
POST   /api/v1/customers
GET    /api/v1/customers/{uuid}

GET    /api/v1/accounts
POST   /api/v1/accounts
GET    /api/v1/accounts/{uuid}
GET    /api/v1/accounts/{uuid}/balance
GET    /api/v1/accounts/{uuid}/ledger

GET    /api/v1/transactions
POST   /api/v1/transactions/deposit      # Idempotency-Key
POST   /api/v1/transactions/withdraw
POST   /api/v1/transactions/transfer
POST   /api/v1/transactions/{uuid}/retry
GET    /api/v1/transactions/{uuid}

# Legacy (deprecated, без named routes): те же пути под /api/...
```

Полный контракт v1 — [ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml) (server `.../api/v1`) · Swagger UI: [http://localhost/api/docs](http://localhost/api/docs) (local, Sail).

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
| **API auth & CRUD** | `Api/AuthApiTest`, `Api/AccountApiTest`, `Api/CustomerApiTest` | Register/login, accounts, customers (часть URL — legacy `/api/*`; `route('api.*')` — v1) |
| **API versioning** | `ApiVersioningTest` | Заголовки v1/legacy, `/api/v1/*`, Sunset на legacy |
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
composer quality     # pint (fix dirty) + stan + rector dry-run + test --coverage
composer ci          # quality:ci — pint --test + stan + rector + test (CI gate)
make ci              # Sail: lint + test + npm run build (паритет с Actions)
composer pint        # fix dirty files
composer stan        # PHPStan level 6
composer rector      # apply refactoring
```

Подробнее: [docs/quality.md](./docs/quality.md).

- `declare(strict_types=1)` — везде.
- `final` на application services и V1 wrappers; **base** API controllers/resources открыты для наследования **`Api\V1\*`**.
- `readonly` на application services — immutability зависимостей через constructor injection.

### CI

GitHub Actions ([`.github/workflows/ci.yml`](./.github/workflows/ci.yml)): на push/PR в `develop`, `master`, `feature/**` — PHP 8.5, Node 22, PostgreSQL 18, Redis 7; `migrate --force`; затем `npm run build`, Pint, PHPStan, Rector dry-run, `php artisan test`.

---

## 12. Осознанные компромиссы

Что **не** сделано, что это означает и чем компенсируется:

| Не сделано | Что это / зачем | Почему не в проекте |
|------------|-----------------|---------------------|
| **Event Sourcing** | История хранится как поток событий, состояние — их проекция; удобно для аудита и replay (событийное хранение состояния)| Избыточно на текущем масштабе; `ledger_entries` + `audit_logs` уже дают audit trail |
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
2. **API v2+ / эволюция JSON** — breaking changes только через новую версию; v1 отделён (`Api/V1`, `Resources/V1`, ADR при необходимости). Rate limiting на API — реализован (ADR-008); дальше — scoped Sanctum tokens.
3. **Outbox + типизированные доменные события** — реализован (ADR-006, ADR-009): `transaction.created` / `transaction.completed` / `transaction.failed` / `transaction.retried`; Kafka transport — Redpanda при `KAFKA_ENABLED=true`.
4. **Read Services** — при усложнении выписок и отчётов; сложное чтение выносится из контроллеров в отдельные сервисы — проще оптимизировать SQL и не раздувать HTTP-слой.
5. **Observability** — Telescope в dev; добавить **OpenAPI lint** в CI (`quality:ci` и `npm run build` уже в [GitHub Actions](./.github/workflows/ci.yml)).

---

## Диаграмма: полный путь deposit

```
┌──────────┐  POST /api/v1/transactions/deposit  ┌───────────────────────┐
│  Client  │ ───────────────────────────────────►│ TransactionController │
└──────────┘   Idempotency-Key + Bearer token    └────────┬──────────────┘
                                                          │
                                                          ▼
                                             ┌───────────────────────┐
                                             │  TransactionService   │
                                             │  createTransactionOnce│
                                             │  dispatchIfNewPending │
                                             └──────────┬────────────┘
                                                        │
                                  ┌─────────────────────┼─────────────────────┐
                                   ▼                    ▼                     ▼
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

*Документ отражает состояние кодовой базы на ветке `develop`. См. также [API versioning](./docs/api-versioning.md), [ADR](./docs/architecture/README.md) и [OpenAPI spec](./docs/openapi/ledgerpay.openapi.yaml).*
