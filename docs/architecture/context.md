# Архитектурный контекст

```text
+--------------------+
| Клиент / Админ     |
+---------+----------+
          |
          | Web UI / API
          v
+--------------------+
| Laravel App        |
| Inertia + API      |
+---------+----------+
          |
          | SQL
          v
+--------------------+
| PostgreSQL         |
| customers          |
| accounts           |
| transactions       |
| ledger_entries     |
| audit_logs         |
+--------------------+

          |
          | Queue jobs
          v
+--------------------+
| Redis Queue        |
+--------------------+
```

## Основные потоки

### Deposit (пополнение)

1. HTTP-запрос
2. валидация payload
3. проверка idempotency key
4. создание транзакции "в ожидании"
5. dispatch `ProcessTransactionJob`
6. *(web UI)* audit `TransactionQueued` — только при первом создании, не при idempotent replay
7. worker блокирует счёт
8. пополнение баланса
9. append credit-записи в ledger
10. статус транзакции → completed
11. audit `TransactionCompleted` (worker)

### Withdraw (снятие)

1. HTTP-запрос
2. валидация payload
3. проверка idempotency key
4. авторизация source-счёта
5. создание транзакции "в ожидании"
6. dispatch `ProcessTransactionJob`
7. *(web UI)* audit `TransactionQueued` — только при первом создании
8. worker блокирует счёт
9. списание с баланса (проверка достаточности средств)
10. append debit-записи в ledger
11. статус транзакции → completed
12. audit `TransactionCompleted` (worker)

### Transfer (перевод)

1. HTTP-запрос
2. валидация payload
3. авторизация source и target счетов
4. создание транзакции "в ожидании"
5. dispatch `ProcessTransactionJob`
6. *(web UI)* audit `TransactionQueued` — только при первом создании
7. worker блокирует транзакцию
8. worker блокирует счета в детерминированном порядке
9. debit source
10. credit target
11. две записи в ledger
12. статус транзакции → completed
13. audit `TransactionCompleted` (worker)
