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
        Schema::create('inbox_ping_receipts', function (Blueprint $table) {
            $table->comment('Демо-побочный эффект обработчика external.ping: однократная отметка успешной обработки inbox-сообщения');

            $table->id()->comment('Внутренний PK');

            $table->foreignId('inbox_message_id')
                ->unique()
                ->comment('Связанное inbox-сообщение; не более одной квитанции на сообщение; удаление inbox запрещено')
                ->constrained('inbox_messages')
                ->restrictOnDelete();

            $table->timestampTz('handled_at')->comment('Момент успешной обработки ping в обработчике');

            $table->timestampTz('created_at')->useCurrent()->comment('Время создания записи');
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления записи');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbox_ping_receipts');
    }
};
