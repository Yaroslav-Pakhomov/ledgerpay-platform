<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Запретить операции UPDATE и DELETE в неизменяемых таблицах реестра и аудита, чтобы сохранить исторические записи и гарантировать целостность данных.

        // Создаём функцию, которая запрещает изменение или удаление записей в таблицах, для которых она используется в триггерах.
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_immutable_table_changes()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Table % is immutable', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql
        ");

        // region Бухгалтерский учёт (история операций)
        // Запрещаем обновление записей в таблице ledger_entries.
        DB::statement('
            CREATE TRIGGER ledger_entries_immutable_update
            BEFORE UPDATE ON ledger_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_immutable_table_changes()
        ');

        // Запрещаем удаление записей из таблицы ledger_entries.
        DB::statement('
            CREATE TRIGGER ledger_entries_immutable_delete
            BEFORE DELETE ON ledger_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_immutable_table_changes()
        ');
        // endregion Бухгалтерский учёт (история операций)

        // region Аудит (логирование)
        // Запрещаем обновление записей в таблице audit_logs.
        DB::statement('
            CREATE TRIGGER audit_logs_immutable_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_immutable_table_changes()
        ');

        // Запрещаем удаление записей из таблицы audit_logs.
        DB::statement('
            CREATE TRIGGER audit_logs_immutable_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION prevent_immutable_table_changes()
        ');
        // endregion Аудит (логирование)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем триггер, запрещающий удаление записей из audit_logs.
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable_delete ON audit_logs');

        // Удаляем триггер, запрещающий обновление записей в audit_logs.
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable_update ON audit_logs');

        // Удаляем триггер, запрещающий удаление записей из ledger_entries.
        DB::statement('DROP TRIGGER IF EXISTS ledger_entries_immutable_delete ON ledger_entries');

        // Удаляем триггер, запрещающий обновление записей в ledger_entries.
        DB::statement('DROP TRIGGER IF EXISTS ledger_entries_immutable_update ON ledger_entries');

        // Удаляем функцию, которая блокирует изменение неизменяемых таблиц.
        DB::statement('DROP FUNCTION IF EXISTS prevent_immutable_table_changes');
    }
};
