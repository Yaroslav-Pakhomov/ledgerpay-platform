# API Versioning

Стабильный публичный контракт LedgerPay:

```text
/api/v1
```

Маршруты без префикса версии (`/api/*`) — **deprecated** compatibility aliases.

## Заголовки ответа

### Versioned API (`/api/v1/*`)

```text
X-API-Version: v1
```

### Legacy API (`/api/*`, кроме docs)

```text
X-API-Version: legacy
Deprecation: true
Sunset: <RFC 7231 date>
Link: </api/v1>; rel="successor-version"
```

## Стратегия совместимости

- JSON-форма ответов v1 (`App\Http\Resources\V1\*`) считается публичным контрактом.
- Breaking changes в теле ответа — новая версия API (`/api/v2`, …).
- Обратно совместимые добавления полей допустимы в текущей v1.

## Пример

```bash
curl http://localhost/api/v1/auth/me \
  -H "Authorization: Bearer <token>"
```
