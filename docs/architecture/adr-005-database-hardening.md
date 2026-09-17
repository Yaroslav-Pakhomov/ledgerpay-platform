# ADR-005: Усиление целостности на уровне PostgreSQL

## Статус

Принято.

## Контекст

Валидации на уровне приложения недостаточны для fintech-подобных систем.

Баги, скрипты, неудачные деплои или прямой SQL-доступ могут обойти Laravel models и services.

[ADR-002](./adr-002-immutable-ledger.md) уже фиксирует immutability ledger на уровне Eloquent; ADR-005 добавляет **database-level** enforcement.

## Решение

Критичные инварианты "силового/прямого" запроса в PostgreSQL:

- баланс счёта не может быть отрицательным;
- сумма транзакции и ledger-записи — строго положительная;
- код валюты — uppercase, 3 символа (ISO 4217);
- форма счетов транзакции соответствует типу (deposit / withdrawal / transfer);
- записи `ledger_entries` неизменяемы (UPDATE/DELETE блокируются триггером);
- записи `audit_logs` неизменяемы (UPDATE/DELETE блокируются триггером);
- записи `reconciliation_reports` неизменяемы (UPDATE/DELETE блокируются триггером, см. [ADR-007](./adr-007-reconciliation.md));
- FK `reconciliation_reports.account_id → accounts.id` с `ON DELETE RESTRICT` (см. [ADR-007](./adr-007-reconciliation.md));
- partial indexes для мониторинга failed/pending транзакций и истории ledger/audit.

## Последствия

Плюсы:

- более сильная целостность данных;
- защита от багов приложения и операционных скриптов;
- выше доверие к историческим данным;
- быстрее служебные запросы мониторинга.

Минусы:

- миграции PostgreSQL-specific (не SQLite);
- тесты должны выполняться на PostgreSQL;
- при добавлении constraints нужен careful migration ordering на существующих данных;
- двойная защита (Eloquent + DB) — два типа исключений в тестах.

## Индексы производительности

Миграция `2026_08_28_053850_add_fintech_performance_indexes.php`:

| Индекс | Таблица | Назначение | Пример запроса |
|--------|---------|------------|----------------|
| `transactions_failed_created_at_idx` | `transactions` | Failed-транзакции, новые первые | `WHERE status = 'failed' ORDER BY created_at DESC` |
| `transactions_pending_created_at_idx` | `transactions` | Pending/processing, старые первые | `WHERE status IN ('pending','processing') ORDER BY created_at ASC` |
| `ledger_entries_account_created_desc_idx` | `ledger_entries` | История счёта | `WHERE account_id = ? ORDER BY created_at DESC` |
| `audit_logs_created_desc_idx` | `audit_logs` | Последние события | `ORDER BY created_at DESC LIMIT N` |
| `customers_email_lower_idx` | `customers` | Поиск по email без учёта регистра | `WHERE lower(email) = lower(?)` |

Partial indexes (`transactions_*`) содержат только строки, подходящие под `WHERE` в определении индекса.

## Проверка индексов (EXPLAIN)

После добавления индексов проверяем, что PostgreSQL их использует на **реалистичном объёме данных**.

Инструменты:

- CLI: `php artisan db:explain` (через Sail: `sail artisan db:explain`);
- PHP: `App\Support\Database\QueryPlanInspector`.

Подробнее: [Query Plan Inspector](../database/query-plan-inspector.md).

### Чеклист

1. **Индекс существует** — `\d table_name` в psql или `\di *idx*`.
2. **Запрос совпадает с паттерном индекса** — те же колонки в `WHERE` / `ORDER BY`; для partial index — то же условие `WHERE`.
3. **В плане нет Seq Scan** (на больших таблицах) — `Indexes used` содержит ожидаемое имя.
4. **Execution time приемлем** — `sail artisan db:explain "..." --analyze`.

### Примеры

```bash
# Failed-мониторинг → transactions_failed_created_at_idx
sail artisan db:explain \
  "SELECT * FROM transactions WHERE status = 'failed' ORDER BY created_at DESC LIMIT 20" \
  --analyze

# История ledger по счёту → ledger_entries_account_created_desc_idx
sail artisan db:explain \
  "SELECT * FROM ledger_entries WHERE account_id = 1 ORDER BY created_at DESC LIMIT 50" \
  --analyze

# Audit feed → audit_logs_created_desc_idx (на больших таблицах)
sail artisan db:explain \
  "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50" \
  --analyze
```

Ожидаемая сводка для ledger:

```
Node types ........ Limit, Index Scan
Indexes used ...... ledger_entries_account_created_desc_idx
Seq Scan .......... no
```

### Seq Scan на dev — это не всегда баг

На таблицах с десятками строк PostgreSQL часто выбирает `Seq Scan + Sort`, даже если индекс создан. Причины:

- overhead индекса выше, чем чтение всей таблицы;
- `SELECT *` требует обращения к heap за каждой колонкой;
- статистика (`pg_class.reltuples`) может быть неточной на маленьких таблицах.

**Критерий проблемы:** `Seq Scan: yes` + рост `Execution time` на тысячах/миллионах строк, а не на 20–50 записях в local.

### Если индекс не используется на большом объёме

1. `ANALYZE table_name;`
2. Проверить совпадение запроса с определением индекса.
3. Посмотреть полный план: `--json`.
4. Для эксперимента (не в production): `SET enable_seqscan = off;` и повторить EXPLAIN.
