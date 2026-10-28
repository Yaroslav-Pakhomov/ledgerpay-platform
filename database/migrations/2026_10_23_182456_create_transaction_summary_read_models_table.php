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
        Schema::create('transaction_summary_read_models', function (Blueprint $table) {
            $table->comment('Проекция для интерфейса: сводка по операции без соединений со счетами и клиентами');

            $table->id()->comment('Внутренний идентификатор строки проекции');

            $table->foreignId('transaction_id')->unique()->comment('Операция в таблице transactions; одна строка проекции на операцию; при удалении операции строка проекции удаляется')->constrained('transactions')->cascadeOnDelete();

            $table->uuid('transaction_uuid')->unique()->comment('Публичный идентификатор операции (копия transactions.uuid)');

            $table->string('type')->comment('Тип финансовой операции (копия transactions.type)');

            $table->string('status')->comment('Статус обработки (копия transactions.status)');

            $table->bigInteger('amount')->comment('Сумма в минорных единицах');

            $table->string('currency', 3)->comment('Код валюты по ISO 4217');

            $table->uuid('source_account_uuid')->nullable()->comment('Публичный идентификатор счёта-источника; пусто для пополнения');

            $table->uuid('target_account_uuid')->nullable()->comment('Публичный идентификатор счёта-получателя; пусто для списания');

            $table->uuid('source_customer_uuid')->nullable()->comment('Клиент отправителя; для отбора «мои операции» по стороне источника');

            $table->string('source_customer_email')->nullable()->comment('Почта клиента-отправителя для панели бэк-офиса');

            $table->uuid('target_customer_uuid')->nullable()->comment('Клиент получателя; для отбора «мои операции» по стороне получателя');

            $table->string('target_customer_email')->nullable()->comment('Почта клиента-получателя для панели бэк-офиса');

            $table->text('failure_reason')->nullable()->comment('Текст причины сбоя после неуспешной обработки в очереди');

            $table->timestamp('processed_at')->nullable()->comment('Копия transactions.processed_at после успешной обработки или пусто');

            $table->timestamp('transaction_created_at')->comment('Копия transactions.created_at — дата создания операции для списков и сортировки');

            $table->timestamp('created_at')->useCurrent()->comment('Время первой записи проекции');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время последнего пересчёта проекции');

            $table->index(['status', 'id']);
            $table->index(['type', 'id']);
            $table->index(['currency', 'id']);
            $table->index(['source_customer_uuid', 'id']);
            $table->index(['target_customer_uuid', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_summary_read_models');
    }
};
