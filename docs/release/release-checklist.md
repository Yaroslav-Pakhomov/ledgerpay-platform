# Чеклист релиза

## Локальная проверка

```bash
./vendor/bin/sail artisan migrate:fresh --seed
# ReadModelsSeeder в DatabaseSeeder; при ручной миграции без seed:
# ./vendor/bin/sail artisan read-models:rebuild --truncate
./vendor/bin/sail artisan test
./vendor/bin/sail composer pint:test
./vendor/bin/sail composer stan
./vendor/bin/sail composer rector:test
./vendor/bin/sail npm run build
```

## Проверка runtime

```bash
./vendor/bin/sail artisan diagnostics:run
./vendor/bin/sail artisan reconciliation:run
./vendor/bin/sail artisan outbox:dispatch-pending
```

## Ручная проверка в браузере

- Открыть `/login`
- Войти как `admin@ledgerpay.test` / `StrongPassword123!`
- Backoffice: dashboard, транзакции, audit, outbox, сверка, **диагностика**
- Войти как `alice@ledgerpay.test` / `StrongPassword123!`
- Создать счёт → поставить deposit в очередь → запустить Horizon → открыть ledger
- (Опционально) Войти как admin → открыть `/horizon` — dashboard очередей

## Queue worker (Horizon)

```bash
./vendor/bin/sail artisan horizon
```

Проверка статуса:

```bash
./vendor/bin/sail artisan horizon:status
```

Остановка для деплоя конфигурации:

```bash
./vendor/bin/sail artisan horizon:terminate
```

## Планировщик

```bash
./vendor/bin/sail artisan schedule:work
```

## Smoke-тест API

```bash
curl -s http://localhost/api/v1/health/live
curl -s http://localhost/api/v1/health/ready
```

## Проверка документации

- [ ] README актуален
- [ ] В OpenAPI server указан `/api/v1`
- [ ] ADR описывают ключевые решения, включая shard queues ([ADR-011](../architecture/adr-011-account-sharded-transaction-queues.md)) и проекции для чтения ([ADR-014](../architecture/adr-014-read-model-projections.md))
- [ ] Сценарий для собеседования открывается из README ([`docs/interview/walkthrough.md`](../interview/walkthrough.md))
- [ ] Личные заметки demo (опционально, локально, не в git): `docs/interview/portfolio-notes.md`
- [ ] В CHANGELOG есть запись о релизе
- [ ] `SECURITY.md` на месте
- [ ] Ссылки из `docs/index.md` открываются
- [ ] После `migrate:fresh --seed` readiness может быть `warning` (demo failed-транзакция) — ожидаемо
