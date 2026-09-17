# ADR-007: Сверка балансов и реестра

## Статус

Принято.

## Контекст

Финансовым системам нужна независимая проверка сохранённых балансов.

Даже при защите на уровне приложения и БД операционные ошибки или будущие баги могут привести к расхождению `accounts.balance` и суммы проводок.

## Решение

LedgerPay выполняет сверку для каждого счёта:

```text
ledger_balance = sum(credits) - sum(debits)
difference     = account_balance - ledger_balance
```

Результат сохраняется в `reconciliation_reports` со статусом `matched` или `mismatched`.

Отчёты — **append-only**: UPDATE/DELETE запрещены на уровне Eloquent (`ReconciliationReport::booted()`) и PostgreSQL-триггеров (defense in depth, см. [ADR-005](./adr-005-database-hardening.md)).

FK `account_id → accounts.id` с `ON DELETE RESTRICT`: счёт, у которого есть отчёты сверки, удалить нельзя.
Прямой DELETE/UPDATE строк `reconciliation_reports` дополнительно блокируется immutability-триггером.

Запуск: Artisan `reconciliation:run`, планировщик (ежечасно), UI бэк-офиса.

## Последствия

Плюсы:

- обнаружение дрейфа балансов;
- поддержка расследований в бэк-офисе;
- проверка целостности реестра;
- повышение операционного доверия.

Минусы:

- сверка выполняется с задержкой (eventual consistency);
- большие объёмы данных требуют пакетной обработки (`--limit`);
- в production-системах может понадобиться учёт начального баланса.
- счёт с отчётами сверки нельзя удалить (`RESTRICT` на FK); для архивации счёта нужна отдельная процедура или soft-delete;
- прямое изменение/удаление отчётов запрещено immutability-триггером (как у `ledger_entries` и `audit_logs`).
