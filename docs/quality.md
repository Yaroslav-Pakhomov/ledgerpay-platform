# Качество кода

Краткий справочник по quality gate LedgerPay. Подробнее — [README_ARCHITECTURE.md](../README_ARCHITECTURE.md) §11.

**Два способа запуска:** `composer …` — на хосте (нужны PHP 8.5+ и `vendor/`); `make …` — через Laravel Sail (`./vendor/bin/sail`).

## Laravel Pint

```bash
composer pint        # fix только изменённые (--dirty)
composer pint:fix    # fix все файлы по правилам Pint
composer pint:test   # check all (CI)
make pint            # Sail: fix all
make pint-test
```

## PHPStan / Larastan

Level: **6**. Paths: `app`, `routes`, `database/factories`, `database/seeders`, `tests` (см. `phpstan.neon`).

```bash
composer stan        # alias: composer phpstan; --memory-limit=1G
make stan
```

## Rector

Сканирует `app`, `routes`, `database`, `tests` (см. `rector.php`; у PHPStan каталог `database` уже — только factories/seeders).

```bash
composer rector:test # dry-run (CI)
composer rector      # apply locally
make rector-test
make rector
```

## Composer-скрипты (сводка)

| Команда | Pint | PHPStan | Rector | Tests |
|--------|------|---------|--------|-------|
| `composer quality` | fix **dirty** | да | dry-run | да, **с coverage** |
| `composer quality:ci` | **check** (`--test`) | да | dry-run | да, без coverage |
| `composer ci` | то же, что `quality:ci` | | | |

Локально перед PR удобно: **`make ci`** (Sail) или **`composer ci`** + **`npm run build`** (или `make build`).

```bash
composer test        # php artisan test --coverage (медленнее)
composer test:ci     # без coverage; так же в CI
```

## Тесты конкурентности (PostgreSQL)

Каталог `tests/Concurrency/`. Требуется `DB_CONNECTION=pgsql` (на SQLite тесты пропускаются). В CI выполняются вместе с полным `php artisan test`.

```bash
./vendor/bin/sail artisan test --testsuite=Concurrency
./vendor/bin/sail artisan test --group=concurrency
```

Подробнее: [README § тесты конкурентности](../README.md#тесты-конкурентности-postgresql).

## Полная проверка (паритет с CI)

**Sail (рекомендуется):**

```bash
make ci    # pint-test + stan + rector-test + test + npm run build
```

**Хост:**

```bash
composer ci
npm run build    # или: ./vendor/bin/sail npm run build
```

Цепочка **`make quality`** — только lint (pint-test, stan, rector-test), без тестов и сборки фронта.

## CI

GitHub Actions ([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)) на push/PR в `develop`, `master`, `feature/**`:

- **PHP 8.5**, **Node 22**
- **PostgreSQL 18**, **Redis 7** (readiness/diagnostics в тестах)
- Подготовка: `.env` из example, `key:generate`, **`migrate --force`**
- Порядок steps: `npm run build` → Pint (`--test`) → PHPStan → Rector dry-run → `php artisan test`

Отдельные job-steps, не один вызов `composer ci`.

## Troubleshooting (Sail)

Если Rector/Pint падает с `Permission denied` в `storage/` — часто после команд от root:

```bash
make fix-perms
# или
./vendor/bin/sail exec -u root laravel.test chown -R sail:sail storage bootstrap/cache
```

`make fix-perms` также выставляет права на кеш Rector.

Rector cache: `storage/framework/cache/rector` (см. `rector.php` → `withCache()`).
