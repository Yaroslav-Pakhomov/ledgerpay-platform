# ADR-011: Account-sharded transaction queues и Laravel Horizon

## Статус

Принято.

## Контекст

После [ADR-003](./adr-003-async-transaction-processing.md) все `ProcessTransactionJob` обрабатывались через одну Redis-очередь. При нескольких worker-процессах:

- не было **упорядочивания операций по одному счёту** на уровне очереди;
- один «горячий» account конкурировал со всеми остальными за workers;
- оператор видел backlog только по одному имени очереди.

Для создания высоконагруженного портфолио-проекта нужен первый шаг: **сегментирование/шардирование маршрутизации по account_id** + **Horizon** для мониторинга и конфигурации workers.

## Решение

### TransactionQueueRouter

`App\Application\Transaction\Routing\TransactionQueueRouter`:

- вычисляет shard (сегмент): `routing_account_id % transaction_shards`;
- имя очереди: `transactions-account-{shard}` (две цифры, например `00` … `15`);
- количество shards: `config('ledgerpay.queues.transaction_shards')`, env `LEDGERPAY_TRANSACTION_QUEUE_SHARDS` (default **16**).

**Routing account id:**

| Тип операции | Ключ |
|--------------|------|
| Deposit | `target_account_id` |
| Withdrawal / transfer | `source_account_id` (последовательность исходящих списаний с одного счёта) |

`TransactionService` dispatch'ит `ProcessTransactionJob` с `->onQueue($router->queueFor($transaction))` при создании и при retry.

Очередь **не** задаётся в constructor job (fallback в job не используется для production path).

### Horizon

- Workers запускаются через **`php artisan horizon`** (Sail: `./vendor/bin/sail artisan horizon`).
- Supervisors в `config/horizon.php`: `transactions-sharded` (все shard-очереди), `outbox`, `default`.
- UI: **`/horizon`** (или `HORIZON_PATH`), доступ по gate **`viewHorizon`** — только пользователь backoffice (`User::isBackOffice()`, web-сессия после `/login`).

### Готовность

`QueueBacklogCheck` суммирует LLEN по **всем** shard-очередям + `outbox` + `default` (с префиксом Redis из конфигурации). Порог warning: суммарно ≥ 1000 job'ов.

### Синхронизация конфигурации

При изменении `LEDGERPAY_TRANSACTION_QUEUE_SHARDS` нужно обновить список очередей в **`config/horizon.php`** (supervisor `transactions-sharded`).

## Последствия

Плюсы:

- один маршрут аккаунта → один shard (сегмент) → FIFO внутри очереди при одном процессе на очередь;
- разные аккаунты → параллельная обработка на разных shards;
- наблюдаемая пропускная способность/сбои через Horizon;
- конфигурация масштабирования, управляемая кодом в репозитории.

Минусы / ограничения:

- **не** снимает "горячие" строки на `accounts.balance` (`lockForUpdate` остаётся узким местом);
- transfer (перевод) **не** сериализует оба счёта глобально — только исходящий источник;
- статический список очередей в `horizon.php` при смене числа сегментов;
- `composer dev` использует `queue:listen` без полного набора shard-очередей — для demo предпочтителен Sail + Horizon.

## Связанные документы

- [ADR-003: Асинхронная обработка транзакций](./adr-003-async-transaction-processing.md)
- [ADR-010: Состояние, готовность и диагностика](./adr-010-health-readiness-diagnostics.md)
