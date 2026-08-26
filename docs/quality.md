# Code Quality

Краткий справочник по quality gate LedgerPay. Подробнее — [README_ARCHITECTURE.md](../README_ARCHITECTURE.md) §11.

## Laravel Pint

```bash
composer pint        # fix dirty files
composer pint:test   # check all (CI)
make pint-test
```

## PHPStan / Larastan

Level: **6**. Paths: `app`, `routes`, `database/factories`, `database/seeders`, `tests`.

```bash
composer stan        # alias: composer phpstan
make stan
```

## Rector

```bash
composer rector:test # dry-run (CI)
composer rector      # apply locally
make rector-test
```

## Полная проверка

```bash
composer ci          # pint + stan + rector + tests
make ci              # + frontend build
npm run build
```

## CI

GitHub Actions (`.github/workflows/ci.yml`):

- PostgreSQL 18
- `pint --test`
- `phpstan analyse`
- `rector process --dry-run`
- `php artisan test`
- `npm run build`

## Troubleshooting (Sail)

Если Rector/Pint падает с `Permission denied` в `storage/` — часто после команд от root:

```bash
make fix-perms
# или
./vendor/bin/sail exec -u root laravel.test chown -R sail:sail storage bootstrap/cache
```

Rector cache: `storage/framework/cache/rector` (см. `rector.php` → `withCache()`).
