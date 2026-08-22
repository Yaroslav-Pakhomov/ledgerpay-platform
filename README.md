# LedgerPay

LedgerPay — fintech backend проект уровня portfolio на Laravel 13, PostgreSQL, Redis Queue, Laravel Sail, Sanctum, Inertia и Vue 3.

Демонстрирует инженерные практики Senior Backend Engineer / Tech Lead в fintech-командах.

## Стек

- Laravel 13, PHP 8.5+
- Laravel Sail, PostgreSQL, Redis Queue
- Laravel Sanctum, Vue 3, Inertia.js, Tailwind CSS
- PHPUnit / Feature Tests
- GitHub Actions — `quality:ci`, frontend build на push/PR

## Домен

- клиенты, счета, ввод/вывод/переводы средств
- неизменяемый реестр и журнал аудита
- асинхронная обработка через очередь, идемпотентность API
- панель управления бэк-офиса

## Архитектура

```text
app/
├── Domain/       Account, Audit, Customer, Ledger, Shared, Transaction
├── Application/  Account, Audit, Auth, Customer, Transaction
├── Http/         Controllers, Middleware, Requests, Resources
└── Support/      ProblemDetails, ...
```

### Ключевые решения

- **Минорные ед.** — 100.50 RUB => 10050
- **Неизменяемый реестр + журнал аудита**
- **Асинхронная** — ожидающая обработки транзакция + `ProcessTransactionJob`
- **Блокировка строк** — `lockForUpdate()`, порядок блокировки по ID
- **Idempotency-Key** (ключ Идемпотентности) на движение денег
- **Problem Details JSON**, **X-Request-Id**

## Документация

| Документ               | Ссылка |
|------------------------|--------|
| Swagger UI             | [http://localhost/api/docs](http://localhost/api/docs) (при `API_DOCS_ENABLED=true`, Sail) |
| OpenAPI YAML           | [ledgerpay.openapi.yaml](./docs/openapi/ledgerpay.openapi.yaml) |
| Swagger (как устроен)  | [swagger.md](./docs/architecture/swagger.md) |
| ADR\*                  | [docs/architecture/README.md](./docs/architecture/README.md) |
| Контекстная диаграмма  | [context.md](./docs/architecture/context.md) |
| Архитектура (подробно) | [README_ARCHITECTURE.md](./README_ARCHITECTURE.md) |

* \*ADR - Architecture Decision Record (запись архитектурного решения).

## Локальный запуск

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Queue worker:

```bash
./vendor/bin/sail artisan queue:work redis --queue=transactions,default
```

Тесты:

```bash
./vendor/bin/sail artisan test
```

## Swagger UI

```bash
# .env (local)
API_DOCS_ENABLED=true
```

> В **production** держите `API_DOCS_ENABLED=false`.

1. Открыть http://localhost/api/docs
2. `POST /auth/login` → скопировать `access_token`
3. **Authorize** → `Bearer <token>`
4. `POST /accounts` → `POST /transactions/deposit` с `Idempotency-Key`
5. Запущен worker → транзакция перейдёт в `completed`

## Демо-пользователи

- Backoffice: `admin@ledgerpay.test` / `StrongPassword123!`
- Клиенты: `/register` или `POST /api/auth/register`

## Пример curl

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice Morgan","email":"alice@example.com","password":"StrongPassword123!"}'

curl -X POST http://localhost/api/transactions/deposit \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -H "Idempotency-Key: deposit-demo-001" \
  -d '{"target_account_uuid":"<uuid>","amount":100000,"currency":"RUB"}'
```

## Что демонстрирует проект

- Модульная структура, на основе принципов DDD
- Безопасное проведение денежных операций: блокировки строк и транзакции БД
- Неизменяемый ledger (debit/credit) + операционный audit log: баланс — текущее состояние, проводки — история; перевод — debit+credit, deposit/withdraw — односторонняя проводка (см. [README_ARCHITECTURE.md §12](./README_ARCHITECTURE.md#12-осознанные-компромиссы))
- Асинхронная обработка через Redis, идемпотентный API
- Аутентификация через Sanctum, политики авторизации, админ-панель
- JSON в формате Problem Details, корреляция запросов (`X-Request-Id`)
- CI: GitHub Actions — Pint, PHPStan, Rector, tests, сборка frontend
- Функциональные тесты, операционный дашборд на Vue/Inertia


## Команды

```bash
make up | vite | fresh | test | worker | build | docs | quality
```

Или через Sail:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan test
./vendor/bin/sail npm run build
```
