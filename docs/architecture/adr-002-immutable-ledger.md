# ADR-002: Неизменяемый ledger (история операций)

## Статус

Принято.

## Контекст

Fintech ledger должен сохранять исторические факты.

Изменение или удаление записи ledger разрушает аудируемость.

## Решение

Записи ledger — append-only.

Приложение запрещает update и delete для `LedgerEntry`.

Каждая запись содержит:

- transaction id
- account id
- direction
- amount
- currency
- balance after operation

Audit log следует тому же правилу неизменяемости (модель `AuditLog`).

## Последствия

Плюсы:

- аудируемость
- проще расследования
- безопаснее reconciliation

Минусы:

- исправления только через compensating transactions
- рост объёма хранения со временем
