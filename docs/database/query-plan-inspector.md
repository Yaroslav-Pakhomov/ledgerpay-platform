# Query Plan Inspector

Dev-инструмент для просмотра планов выполнения SQL в PostgreSQL.

Состоит из:

- **`App\Support\Database\QueryPlanInspector`** — PHP-обёртка над `EXPLAIN` / `EXPLAIN ANALYZE`;
- **`php artisan db:explain`** — CLI-команда для локальной отладки.

> Только PostgreSQL. SQLite в тестах не поддерживается этим инструментом.

---

## Быстрый старт

```bash
# План без выполнения запроса (безопасно для SELECT/INSERT/UPDATE)
sail artisan db:explain "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50"

# План с реальным выполнением и временем
sail artisan db:explain "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50" --analyze

# Сырой JSON-план PostgreSQL
sail artisan db:explain "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50" --json

# Другое подключение из config/database.php
sail artisan db:explain "SELECT 1" --connection=pgsql
```

Команда доступна в окружениях `local` и `testing`. В других окружениях запрашивает подтверждение.

---

## Сводка в терминале

После `EXPLAIN` команда выводит краткую сводку через `QueryPlanInspector::summarize()`:

| Поле | Значение |
|------|----------|
| **Node types** | Типы узлов плана (Limit, Index Scan, Seq Scan, Sort, …) |
| **Indexes used** | Имена задействованных индексов (`Index Name` в JSON) |
| **Seq Scan** | `yes` — есть полное сканирование таблицы; `no` — нет |
| **Planning time** | Время построения плана (только с `--analyze`) |
| **Execution time** | Время выполнения (только с `--analyze`) |

### Пример «хорошего» плана

```
Node types ........ Limit, Index Scan
Indexes used ...... ledger_entries_account_created_desc_idx
Seq Scan .......... no
```

PostgreSQL использует индекс и не читает таблицу целиком.

### Пример «Seq Scan на маленькой таблице»

```
Node types ........ Limit, Sort, Seq Scan
Indexes used ...... —
Seq Scan .......... yes
Execution time .... 0.059 ms
```

На dev с десятками строк это **нормально**: планировщик считает полное чтение дешевле, чем обращение к индексу. Индекс может существовать, но не использоваться.

---

## EXPLAIN vs EXPLAIN ANALYZE

| | `EXPLAIN` | `EXPLAIN ANALYZE` |
|---|-----------|-------------------|
| Выполняет запрос | Нет | **Да** |
| Показывает реальное время | Нет | Да |
| Безопасность | Безопасно для любого SQL | **INSERT/UPDATE/DELETE блокируются** в коде |
| Когда использовать | Быстрая проверка плана | Оценка реальной производительности |

`EXPLAIN ANALYZE` для мутирующих запросов заблокирован намеренно — см. `QueryPlanInspector::isMutatingSql()`.

---

## PHP API

```php
use App\Support\Database\QueryPlanInspector;
use App\Models\Transaction;

// Сырой SQL
$plan = QueryPlanInspector::explain(
    "SELECT * FROM transactions WHERE status = 'failed' ORDER BY created_at DESC LIMIT 20"
);

// Eloquent / Query Builder
$plan = QueryPlanInspector::explain(
    Transaction::query()->where('status', 'failed')->orderByDesc('created_at')->limit(20)
);

// С реальным выполнением
$plan = QueryPlanInspector::explainAnalyze($sql);

// Человекочитаемая сводка
$summary = QueryPlanInspector::summarize($plan);
// [
//     'node_types'        => ['Limit', 'Index Scan'],
//     'indexes'           => ['transactions_failed_created_at_idx'],
//     'planning_time_ms'  => null,          // только после explainAnalyze
//     'execution_time_ms' => null,
//     'uses_seq_scan'     => false,
// ]
```

---

## На что смотреть в плане

### 1. Seq Scan на больших таблицах

`Seq Scan: yes` на таблицах с тысячами+ строк — повод проверить:

- есть ли подходящий индекс;
- совпадает ли `WHERE` / `ORDER BY` с колонками индекса;
- актуальна ли статистика (`ANALYZE table_name`).

### 2. Sort рядом с Seq Scan

```
Limit → Sort → Seq Scan
```

Часто означает: «прочитали всё → отсортировали → обрезали LIMIT». На больших объёмах дорого. Ожидаемый паттерн для `ORDER BY … LIMIT N` — **Index Scan** без отдельного Sort.

### 3. Indexes used = —

Индекс не используется. Возможные причины:

| Причина | Что делать |
|---------|------------|
| Таблица слишком мала | Норма на dev; проверить на реалистичном объёме данных |
| Индекс не создан | `migrate:status`, `\d table_name` в psql |
| Запрос не совпадает с индексом | Сверить WHERE/ORDER BY с определением индекса |
| `SELECT *` | Индекс не covering — PG может выбрать Seq Scan, если строк мало |
| Устаревшая статистика | `ANALYZE` |

### 4. Partial index

Частичный индекс используется только если `WHERE` **совместим** с условием индекса.

```sql
-- индекс: WHERE status = 'failed'
-- ✅ использует transactions_failed_created_at_idx
SELECT * FROM transactions WHERE status = 'failed' ORDER BY created_at DESC LIMIT 20;

-- ❌ partial index не подходит
SELECT * FROM transactions WHERE status = 'completed' ORDER BY created_at DESC LIMIT 20;
```

### 5. Execution time

С `--analyze` смотрите **Execution time**, а не только тип узлов. Seq Scan на 32 строках за 0.059 ms — не проблема.

---

## JSON-план: ключевые поля

```json
{
  "Plan": {
    "Node Type": "Limit",
    "Total Cost": 2.68,
    "Plan Rows": 20,
    "Plans": [
      {
        "Node Type": "Index Scan",
        "Index Name": "audit_logs_created_desc_idx",
        "Relation Name": "audit_logs"
      }
    ]
  },
  "Planning Time": 0.587,
  "Execution Time": 0.059
}
```

| Поле | Смысл |
|------|-------|
| `Node Type` | Тип операции |
| `Index Name` | Используемый индекс |
| `Relation Name` | Таблица |
| `Plan Rows` | Оценка числа строк (не факт!) |
| `Total Cost` | Условная стоимость для планировщика |
| `Sort Key` | Колонки сортировки |
| `Execution Time` | Реальное время (только ANALYZE) |

---

## Проверочные запросы для индексов ADR-005

См. [ADR-005](../architecture/adr-005-database-hardening.md#проверка-индексов-explain).

---

## Ограничения

- Только PostgreSQL (`QueryPlanInspector::assertPostgreSql()`).
- `db:explain` — dev/local инструмент, не для production.
- `summarize()` собирает только `Node Type` и `Index Name`; сложные планы смотрите через `--json`.
- `EXPLAIN` без `ANALYZE` строит план по статистике — на маленьких таблицах может отличаться от production.
