<?php

declare(strict_types=1);

use App\Domain\Customer\Enums\CustomerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Клиенты системы
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            // Numeric ID остается внутренним DB primary key.
            $table->id()->comment('остается внутренним ID');

            // UUID используем как публичный идентификатор.
            $table->uuid('uuid')->unique()->comment('UUID используем как публичный идентификатор');

            // Текстовые данные
            $table->string('name')->comment('Имя/ФИО');
            $table->string('email')->unique()->comment('Почта');

            $table->string('status')->default(CustomerStatus::Active->value)->comment('Статус клиента/заказчика');

            $table->timestamp('created_at')->useCurrent()->comment('Время создания');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
