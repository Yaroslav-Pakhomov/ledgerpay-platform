<?php

declare(strict_types=1);

use App\Domain\Account\Enums\AccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Счета клиентов
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            // Numeric ID остается внутренним DB primary key.
            $table->id()->comment('остается внутренним ID');

            // UUID используем как публичный идентификатор.
            $table->uuid('uuid')->unique()->comment('UUID используем как публичный идентификатор');
            $table->string('status')->default(AccountStatus::Active->value)->comment('Статус банковского счета');

            // ISO 4217 currency code: RUB, USD, EUR.
            $table->string('currency', 3)->comment('Код валюты');

            // Деньги храним только в minor units:
            // 100.50 RUB => 10050.
            // Никаких decimal/float для балансов.
            $table->bigInteger('balance')->default(0)->comment('Баланс');

            // FK
            $table->foreignId('customer_id')->comment('ID Заказчика')->constrained('customers')->cascadeOnUpdate()->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent()->comment('Время создания');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            $table->index(['customer_id', 'currency']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
