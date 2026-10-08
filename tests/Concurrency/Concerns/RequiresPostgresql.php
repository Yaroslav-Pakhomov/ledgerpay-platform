<?php

declare(strict_types=1);

namespace Tests\Concurrency\Concerns;

/**
 * Тесты конкурентности опираются на PostgreSQL (блокировки строк, уникальность, взаимные блокировки).
 *
 * При SQLite тест пропускается, чтобы локальный `DB_CONNECTION=sqlite` не ломал `artisan test`.
 *
 * Вызов {@see setUpRequiresPostgresql()} — из {@see setUp()} тест-класса.
 */
trait RequiresPostgresql
{
    /**
     * Помечает тест пропущенным, если `database.default` не `pgsql`.
     */
    protected function setUpRequiresPostgresql(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped(
                'Тесты конкурентности требуют PostgreSQL (DB_CONNECTION=pgsql).',
            );
        }
    }
}
