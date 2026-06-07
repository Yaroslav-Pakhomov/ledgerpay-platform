<?php

declare(strict_types=1);

use App\Domain\Transaction\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Операции
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            // Numeric ID остается внутренним DB primary key.
            $table->id()->comment('остается внутренним ID');

            // UUID используем как публичный идентификатор.
            $table->uuid('uuid')->unique()->comment('UUID используем как публичный идентификатор');

            $table->string('type')->comment('Тип финансовой операции');
            $table->string('status')->default(TransactionStatus::Pending->value)->comment('Статус обработки транзакции');
            $table->string('currency', 3)->comment('Код валюты');

            // Пока unique на всю таблицу.
            // Позже можно усилить scoped idempotency по клиенту/endpoint.
            $table->string('idempotency_key')->unique()->comment('Ключ идемпотентности');

            $table->text('failure_reason')->nullable()->comment('Причина сбоя');

            // Int
            $table->bigInteger('amount')->comment('Сумма');

            // FK
            $table->foreignId('source_account_id')->nullable()->comment('ID Отправителя')->constrained('accounts');
            $table->foreignId('target_account_id')->nullable()->comment('ID Получателя')->constrained('accounts');

            $table->timestamp('processed_at')->nullable()->comment('Время обработки');

            $table->timestamp('created_at')->useCurrent()->comment('Время создания');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
