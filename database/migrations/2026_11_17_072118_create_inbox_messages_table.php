<?php

declare(strict_types=1);

use App\Domain\Inbox\Enums\InboxStatus;
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
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->comment('Transactional inbox: идемпотентный приём внешних событий и асинхронная обработка');

            $table->id()->comment('Внутренний PK');
            $table->uuid('uuid')->unique()->comment('Публичный идентификатор inbox-записи');

            $table->string('source', 100)->comment('Источник интеграции (провайдер), например demo-provider');
            $table->string('external_message_id', 255)->comment('Идемпотентный ID сообщения у провайдера; уникален в паре с source');
            $table->string('event_name', 150)->comment('Имя события для маршрутизации обработчика, например external.ping');

            $table->char('payload_hash', 64)->comment('SHA-256 канонического JSON (event_name + payload); проверка конфликта при повторной доставке');

            $table->jsonb('payload')->comment('Тело входящего события (JSON)');
            $table->jsonb('headers')->nullable()->comment('Метаданные webhook (заголовки запроса и пр.)');

            $table->string('status', 20)->default(InboxStatus::Received->value)->comment('Жизненный цикл: received, processing, processed, failed, dead, ignored');
            $table->unsignedSmallInteger('attempts')->default(0)->comment('Число попыток обработки');

            $table->timestampTz('received_at')->comment('Момент приёма сообщения (webhook)');
            $table->timestampTz('available_at')->comment('Время, с которого сообщение снова допустимо к dispatch (в т.ч. после backoff)');

            $table->timestampTz('dispatch_lease_until')->nullable()->comment('Аренда dispatch: пока не истекла, повторно не ставится ProcessInboxMessageJob');
            $table->timestampTz('processed_at')->nullable()->comment('Время успешной обработки');
            $table->timestampTz('last_failed_at')->nullable()->comment('Время последней неуспешной попытки');

            $table->string('last_error_code', 100)->nullable()->comment('Краткий код/класс последней ошибки обработки');
            $table->text('last_error')->nullable()->comment('Текст последней ошибки обработки (обрезается в приложении)');

            $table->timestampTz('created_at')->useCurrent()->comment('Время создания строки');
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время последнего обновления строки');

            $table->unique(['source', 'external_message_id'], 'inbox_source_external_unique');

            $table->index(['status', 'available_at', 'dispatch_lease_until'], 'inbox_dispatch_idx');

            $table->index(['source', 'event_name'], 'inbox_source_event_idx');
            $table->index(['received_at'], 'inbox_received_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
