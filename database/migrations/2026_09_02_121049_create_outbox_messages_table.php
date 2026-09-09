<?php

declare(strict_types=1);

use App\Domain\Outbox\Enums\OutboxStatus;
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
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->comment('Transactional outbox для надёжной публикации доменных событий');

            $table->id()->comment('остается внутренним ID');
            $table->uuid('uuid')->unique()->comment('UUID используем как публичный идентификатор');

            $table->string('event_name')->comment('Имя доменного события, например transaction.created');
            $table->string('aggregate_type')->comment('Класс агрегата (FQCN модели)');
            $table->unsignedBigInteger('aggregate_id')->comment('ID агрегата');
            $table->uuid('aggregate_uuid')->nullable()->comment('Публичный UUID агрегата');

            $table->jsonb('payload')->comment('Тело события (JSON)');
            $table->jsonb('headers')->nullable()->comment('Метаданные публикации (request_id, occurred_at и т.д.)');

            $table->string('status')->default(OutboxStatus::Pending->value)->comment('Статус публикации: pending, processing, published, failed');
            $table->unsignedSmallInteger('attempts')->default(0)->comment('Количество попыток публикации');

            $table->timestamp('available_at')->nullable()->comment('Время, когда сообщение доступно для повторной отправки');
            $table->timestamp('published_at')->nullable()->comment('Время успешной публикации');
            $table->text('last_error')->nullable()->comment('Текст последней ошибки публикации');

            $table->timestamp('created_at')->useCurrent()->comment('Время создания');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            // IDx
            $table->index(['status', 'available_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['event_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
