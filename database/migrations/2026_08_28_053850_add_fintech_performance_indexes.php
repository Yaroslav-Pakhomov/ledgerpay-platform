<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Применение миграции.
     *
     * Создаём индексы для ускорения наиболее частых запросов:
     * - выборки транзакций по статусу и дате;
     * - выборки записей ledger по аккаунту и дате;
     * - сортировки audit-логов по дате;
     * - регистронезависимого поиска клиентов по email.
     */
    public function up(): void
    {
        /**
         * Частичный индекс для неуспешных транзакций.
         *
         * Используется для запросов со статусом `failed`,
         * когда записи сортируются от новых к старым.
         *
         * Пример:
         * WHERE status = 'failed'
         * ORDER BY created_at DESC
         */
        DB::statement("
            CREATE INDEX IF NOT EXISTS transactions_failed_created_at_idx
            ON transactions (created_at DESC)
            WHERE status = 'failed'
        ");

        /**
         * Частичный индекс для транзакций, которые ещё находятся в обработке.
         *
         * Индекс содержит только записи со статусами `pending` и `processing`
         * и ускоряет выборку с сортировкой от старых транзакций к новым.
         *
         * Пример:
         * WHERE status IN ('pending', 'processing')
         * ORDER BY created_at ASC
         */
        DB::statement("
            CREATE INDEX IF NOT EXISTS transactions_pending_created_at_idx
            ON transactions (created_at ASC)
            WHERE status IN ('pending', 'processing')
        ");

        /**
         * Составной индекс для записей ledger_entries.
         *
         * Ускоряет получение операций конкретного аккаунта,
         * отсортированных от новых к старым.
         *
         * Пример:
         * WHERE account_id = ?
         * ORDER BY created_at DESC
         */
        DB::statement('
            CREATE INDEX IF NOT EXISTS ledger_entries_account_created_desc_idx
            ON ledger_entries (account_id, created_at DESC)
        ');

        /**
         * Индекс для сортировки audit-логов по дате создания.
         *
         * Полезен при выводе последних событий системы.
         *
         * Пример:
         * ORDER BY created_at DESC
         */
        DB::statement('
            CREATE INDEX IF NOT EXISTS audit_logs_created_desc_idx
            ON audit_logs (created_at DESC)
        ');

        /**
         * Функциональный индекс для регистронезависимого поиска по email.
         *
         * Индекс будет использоваться в запросах, где email сравнивается
         * через функцию LOWER().
         *
         * Пример:
         * WHERE lower(email) = lower(?)
         */
        DB::statement('
            CREATE INDEX IF NOT EXISTS customers_email_lower_idx
            ON customers (lower(email))
        ');
    }

    /**
     * Откат миграции.
     *
     * Удаляем все индексы, созданные в методе up().
     */
    public function down(): void
    {
        // Удаляем индекс для регистронезависимого поиска клиентов по email.
        DB::statement('DROP INDEX IF EXISTS customers_email_lower_idx');

        // Удаляем индекс сортировки audit-логов по дате создания.
        DB::statement('DROP INDEX IF EXISTS audit_logs_created_desc_idx');

        // Удаляем составной индекс account_id + created_at для ledger_entries.
        DB::statement('DROP INDEX IF EXISTS ledger_entries_account_created_desc_idx');

        // Удаляем частичный индекс для pending/processing транзакций.
        DB::statement('DROP INDEX IF EXISTS transactions_pending_created_at_idx');

        // Удаляем частичный индекс для failed транзакций.
        DB::statement('DROP INDEX IF EXISTS transactions_failed_created_at_idx');
    }
};
