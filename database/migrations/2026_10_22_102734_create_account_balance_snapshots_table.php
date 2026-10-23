<?php

declare(strict_types=1);

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
        Schema::create('account_balance_snapshots', function (Blueprint $table) {
            $table->comment('Снимок после N проводок');

            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();

            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();

            $table->bigInteger('balance');
            $table->string('currency', 3);

            $table->timestamp('snapshotted_at');
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'id']);
            $table->index(['account_id', 'snapshotted_at']);
            $table->unique(['account_id', 'ledger_entry_id']);
        });

        DB::statement('CREATE INDEX IF NOT EXISTS account_balance_snapshots_id_desc_idx ON account_balance_snapshots (id DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS account_balance_snapshots_account_id_id_desc_idx ON account_balance_snapshots (account_id, id DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_balance_snapshots');
    }
};
