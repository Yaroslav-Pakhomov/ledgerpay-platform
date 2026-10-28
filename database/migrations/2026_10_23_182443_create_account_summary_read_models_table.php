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
        Schema::create('account_summary_read_models', function (Blueprint $table) {
            $table->comment('Проекция для интерфейса: сводка по счёту без соединений с клиентами и реестром проводок');

            $table->id()->comment('Внутренний идентификатор строки проекции');

            $table->foreignId('account_id')->unique()->comment('Счёт в таблице accounts; одна строка проекции на счёт; при удалении счёта строка проекции удаляется')->constrained('accounts')->cascadeOnDelete();

            $table->uuid('account_uuid')->unique()->comment('Публичный идентификатор счёта (копия accounts.uuid)');

            $table->foreignId('customer_id')->comment('Владелец счёта; для фильтра списка счетов в личном кабинете клиента')->constrained('customers')->cascadeOnDelete();

            $table->uuid('customer_uuid')->comment('Публичный идентификатор клиента (копия customers.uuid)');

            $table->string('customer_name')->comment('Имя клиента на момент обновления проекции');

            $table->string('customer_email')->comment('Адрес электронной почты клиента на момент обновления проекции');

            $table->string('currency', 3)->comment('Код валюты по ISO 4217');

            $table->bigInteger('balance')->comment('Баланс в минорных единицах (копия accounts.balance на момент обновления проекции)');

            $table->string('status')->comment('Статус счёта (строковое значение, как в accounts.status)');

            $table->unsignedBigInteger('ledger_entries_count')->default(0)->comment('Число проводок по счёту на момент обновления проекции');

            $table->timestamp('last_ledger_entry_at')->nullable()->comment('Время создания последней проводки в реестре или пусто, если проводок нет');

            $table->timestamp('created_at')->useCurrent()->comment('Время первой записи проекции');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время последнего пересчёта проекции');

            $table->index(['customer_id', 'id']);
            $table->index(['status', 'id']);
            $table->index(['currency', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_summary_read_models');
    }
};
