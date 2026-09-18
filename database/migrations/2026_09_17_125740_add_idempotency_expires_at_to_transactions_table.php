<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('idempotency_expires_at')->nullable()->after('idempotency_key')
                ->comment('Срок действия ключа идемпотентности; после истечения ключ очищается командой idempotency:prune-expired');

            $table->index('idempotency_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['idempotency_expires_at']);
            $table->dropColumn('idempotency_expires_at');
        });
    }
};
