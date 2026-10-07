# Архитектурный контекст

```text
+--------------------+
| Клиент / Админ     |
+---------+----------+
          |
          | Web UI / REST API (/api/v1)
          v
+--------------------+
| Laravel App        |
| Inertia + Sanctum  |
+----+-----------+---+
     |             |
     | SQL         | Queue jobs
     v             v
+-------------+  +----------------+
| PostgreSQL  |  | Redis Queue    |
| customers   |  | transactions   |
| accounts    |  | outbox         |
| transactions|  +----------------+
| ledger_entries
| audit_logs
| outbox_messages
| reconciliation_reports
+-------------+
          |
          | (опционально, KAFKA_ENABLED=true)
          v
+--------------------+
| Kafka / Redpanda   |
| domain-events topic|
+--------------------+
```

## Основные потоки

### Deposit (пополнение)

1. HTTP-запрос
2. валидация данных
3. проверка ключа идемпотентности (idempotency key)
4. создание транзакции «в ожидании» (pending)
5. dispatch `ProcessTransactionJob`
6. *(web UI)* audit `TransactionQueued` — только при первом создании, не при idempotent повторе
7. worker блокирует счёт
8. пополнение баланса
9. append credit-записи в реестр
10. outbox: доменное событие (например `transaction.completed`) в той же DB-транзакции, что и проводки
11. статус транзакции → completed
12. audit `TransactionCompleted` (worker)
13. *(async)* `outbox:dispatch-pending` → публикация (log / Kafka)

### Withdraw (снятие)

1. HTTP-запрос
2. валидация данных
3. проверка ключа идемпотентности (idempotency key)
4. авторизация source-счёта
5. создание транзакции «в ожидании» (pending)
6. dispatch `ProcessTransactionJob`
7. *(web UI)* audit `TransactionQueued` — только при первом создании
8. worker блокирует счёт
9. списание с баланса (проверка достаточности средств)
10. append debit-записи в реестр
11. outbox-событие
12. статус → completed
13. audit `TransactionCompleted` (worker)

### Transfer (перевод)

1. HTTP-запрос
2. валидация данных
3. проверка ключа идемпотентности (idempotency key)
4. авторизация source и target счетов
5. создание транзакции «в ожидании» (pending)
6. dispatch `ProcessTransactionJob`
7. *(web UI)* audit `TransactionQueued` — только при первом создании
8. worker блокирует транзакцию
9. worker блокирует счета в детерминированном порядке
10. debit source
11. credit target
12. две записи в реестр
13. outbox-событие
14. статус → completed
15. audit `TransactionCompleted` (worker)

### Сверка (reconciliation)

Планировщик или команда `reconciliation:run` сравнивает `accounts.balance` с балансом, восстановленным из ledger; результат — `reconciliation_reports`.

### Наблюдаемость (ops)

```text
GET /api/v1/health/live
GET /api/v1/health/ready
/backoffice/diagnostics
```
