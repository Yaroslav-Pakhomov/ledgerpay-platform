<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS transactions_id_desc_idx ON transactions (id DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS ledger_entries_account_id_id_desc_idx ON ledger_entries (account_id, id DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS audit_logs_id_desc_idx ON audit_logs (id DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS outbox_messages_id_desc_idx ON outbox_messages (id DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS reconciliation_reports_id_desc_idx ON reconciliation_reports (id DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS reconciliation_reports_id_desc_idx');
        DB::statement('DROP INDEX IF EXISTS outbox_messages_id_desc_idx');
        DB::statement('DROP INDEX IF EXISTS audit_logs_id_desc_idx');
        DB::statement('DROP INDEX IF EXISTS ledger_entries_account_id_id_desc_idx');
        DB::statement('DROP INDEX IF EXISTS transactions_id_desc_idx');
    }
};
