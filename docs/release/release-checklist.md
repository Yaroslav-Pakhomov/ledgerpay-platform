# Чеклист релиза

## Локальная проверка

```bash
./vendor/bin/sail artisan migrate:fresh --seed
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
- Создать счёт → поставить deposit в очередь → запустить worker → открыть ledger

## Queue worker

```bash
./vendor/bin/sail artisan queue:work redis --queue=transactions,outbox,default
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
- [ ] ADR описывают ключевые решения ([`docs/architecture/`](../architecture/))
- [ ] Сценарий для собеседования открывается из README ([`docs/interview/walkthrough.md`](../interview/walkthrough.md))
- [ ] Личные заметки demo (опционально, локально, не в git): `docs/interview/portfolio-notes.md`
- [ ] В CHANGELOG есть запись о релизе
- [ ] `SECURITY.md` на месте
- [ ] Ссылки из `docs/index.md` открываются
- [ ] После `migrate:fresh --seed` readiness может быть `warning` (demo failed-транзакция) — ожидаемо
