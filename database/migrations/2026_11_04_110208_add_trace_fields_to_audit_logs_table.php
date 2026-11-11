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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('trace_id', 32)->nullable()->comment('Идентификатор трассировки OpenTelemetry (32 hex); сопоставление с логами и Jaeger, не замена request_id');
            $table->string('span_id', 16)->nullable()->comment('Идентификатор сегмента трассировки (16 hex) в момент записи аудита');
            $table->index('trace_id', 'audit_logs_trace_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_trace_id_index');
            $table->dropColumn('span_id');
            $table->dropColumn('trace_id');
        });
    }
};
