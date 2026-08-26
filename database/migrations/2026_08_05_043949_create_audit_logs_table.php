<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создаёт таблицу immutable audit trail ({@see AuditLog}).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->comment('Immutable журнал аудита: auth, счета, транзакции, backoffice');

            $table->id()->comment('остается внутренним ID');

            // FK: пользователь, инициировавший действие.
            // Nullable — для системных/фоновых событий без явного actor.
            $table->foreignId('actor_user_id')
                ->nullable()
                ->comment('ID пользователя, выполнившего действие')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Значение AuditAction enum, например user_logged_in.
            $table->string('action')->comment('Тип аудируемого действия');

            // Полиморфная ссылка на затронутую сущность (Eloquent model).
            // entity_type — FQCN модели, например App\Models\User.
            // entity_id — numeric PK; entity_uuid — публичный UUID, если есть у модели.
            $table->string('entity_type')->nullable()->comment('Класс затронутой сущности');
            $table->unsignedBigInteger('entity_id')->nullable()->comment('ID сущности');
            $table->uuid('entity_uuid')->nullable()->comment('Публичный UUID сущности');

            // Дополнительный контекст события (amount, status, reason и т.д.).
            $table->jsonb('metadata')->nullable()->comment('Дополнительные данные события');

            // HTTP-контекст запроса (на web может быть null).
            $table->string('request_id')->nullable()->comment('Correlation ID запроса (X-Request-Id)');
            $table->ipAddress('ip_address')->nullable()->comment('IP-адрес клиента');
            $table->string('user_agent')->nullable()->comment('User-Agent клиента');

            $table->timestamp('created_at')->useCurrent()->comment('Время создания');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            // Индексы для backoffice-фильтров и расследований.
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('entity_uuid');
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
