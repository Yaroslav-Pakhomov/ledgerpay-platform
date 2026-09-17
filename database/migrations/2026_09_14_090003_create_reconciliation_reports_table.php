<?php

declare(strict_types=1);

use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->comment('Отчёты сверки сохранённого баланса счёта vs баланса по реестру');

            $table->id()->comment('Внутренний PK');
            $table->uuid('uuid')->unique()->comment('Публичный UUID отчёта');

            // FK
            $table->foreignId('account_id')->comment('Счёт, для которого выполнена сверка. ON DELETE RESTRICT — нельзя удалить счёт с отчётами')->constrained('accounts')->restrictOnDelete();

            $table->bigInteger('account_balance')->comment('Баланс из accounts.balance (minor units)');
            $table->bigInteger('ledger_balance')->comment('Баланс, восстановленный из реестра (minor units)');
            $table->bigInteger('difference')->comment('account_balance − ledger_balance');

            $table->string('currency', 3)->comment('ISO-4217 валюта счёта');

            $table->string('status')->default(ReconciliationStatus::Matched->value)->comment('matched — совпадает; mismatched — расхождение');

            $table->timestamp('checked_at')->comment('Момент выполнения сверки');

            $table->jsonb('metadata')->nullable()->comment('Служебные данные (account_uuid, ledger_entries_count)');

            $table->timestamp('created_at')->useCurrent()->comment('Время создания');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            // IDx
            $table->index(['status', 'checked_at']);
            $table->index(['account_id', 'checked_at']);
        });

        // region Запрет удаления/обновления
        // Запрещаем обновление записей в таблице reconciliation_reports.
        DB::statement('
            CREATE TRIGGER reconciliation_reports_immutable_update
            BEFORE UPDATE ON reconciliation_reports
            FOR EACH ROW
            EXECUTE FUNCTION prevent_immutable_table_changes()
        ');

        // Запрещаем удаление записей из таблицы reconciliation_reports.
        DB::statement('
            CREATE TRIGGER reconciliation_reports_immutable_delete
            BEFORE DELETE ON reconciliation_reports
            FOR EACH ROW
            EXECUTE FUNCTION prevent_immutable_table_changes()
        ');
        // endregion Запрет удаления/обновления
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем триггер, запрещающий удаление записей из reconciliation_reports.
        DB::statement('DROP TRIGGER IF EXISTS reconciliation_reports_immutable_delete ON reconciliation_reports');
        // Удаляем триггер, запрещающий обновление записей в reconciliation_reports.
        DB::statement('DROP TRIGGER IF EXISTS reconciliation_reports_immutable_update ON reconciliation_reports');

        Schema::dropIfExists('reconciliation_reports');
    }
};
