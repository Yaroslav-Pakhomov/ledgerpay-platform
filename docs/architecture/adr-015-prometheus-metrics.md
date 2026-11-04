# ADR-015: Операционные метрики Prometheus

## Статус

Принято.

## Контекст

LedgerPay — платформа обработки финансовых операций (Laravel, PostgreSQL, Redis, Horizon, outbox, сверка, проекции для чтения).

Состояние и готовность ([ADR-010](./adr-010-health-readiness-diagnostics.md)) и Horizon/Telescope не заменяют мониторинг временных рядов и оповещения.

Нужна видимость:

- операций по статусам;
- отставания outbox;
- давления на Redis-очереди (в т.ч. shard-очереди `transactions-account-XX`);
- свежести сверки;
- грубого состояния проекций (read models);
- успешности сбора метрик.

## Решение

- Пакет **spatie/laravel-prometheus**, пространство имён **`ledgerpay`**, URL **`/metrics`**.
- Тяжёлые агрегаты — только в Artisan **`ledgerpay:metrics:refresh`** (планировщик ~1 мин).
- Значения хранятся в Redis (**`MetricsSnapshot`**); при опросе Prometheus читается снимок.
- Доступ к `/metrics` — **токен Bearer** + рекомендация сетевых ограничений (private network, ACL прокси).

## Локальный стенд

- Docker Compose **profile `observability`**: сервисы Prometheus и Grafana в сети Sail.
- Scrape: `deploy/prometheus/prometheus.yml` (из example), Bearer через **`credentials_file`** (`deploy/prometheus/secrets/`, не в Git).
- Grafana: provisioning datasource Prometheus + file dashboard `deploy/grafana/dashboards/ledgerpay-operational.json`.
- Бэк-офис: опциональная ссылка `LEDGERPAY_GRAFANA_URL` на UI Grafana; runtime приложения от Grafana **не зависит**.

## Безопасность

- Не использовать метки с высокой кардинальностью (`account_id`, `transaction_id`, `idempotency_key`).
- Не экспортировать суммы денег и персональные данные.
- Токен только в заголовке `Authorization` (токен создаётся командой "openssl rand -hex 32").

## Семантика метрик

- **`transactions_*`**, **`outbox_*`** — шкалы (Gauge, текущее состояние), не счётчики (Counter).
- **`reconciliation_last_check_age_seconds`** — `-1`, если сверка ни разу не запускалась.
- **`projection_*_row_gap`** — не отставание по событиям и не актуальность данных.
- **`queue_*`** — длина ready-списков Redis (LLEN); reserved и delayed job'ы не учитываются; метрики по каждому shard — вне объёма коммита.

## Последствия

**Плюсы:** совместимость с Prometheus/Grafana; меньше нагрузки на БД при опросе; разделение сбора и экспорта.

**Минусы:** метрики обновляются с задержкой; зависимость от планировщика и Redis-снимка; устаревший или пустой снимок может выглядеть как нули.

## Будущие улучшения

- Контрольные точки для отставания проекций; метрики очередей по shard; правила оповещений (Alertmanager); OpenTelemetry — трассировка.
