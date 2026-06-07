<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Бухгалтерский журнал
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            // Numeric ID остается внутренним DB primary key.
            $table->id()->comment('остается внутренним ID');

            $table->string('direction');
            $table->string('currency', 3)->comment('Код валюты');
            $table->bigInteger('amount')->comment('Сумма');

            // Баланс после конкретной ledger-записи.
            // Это важно для аудита и расследований.
            $table->bigInteger('balance_after')->comment('Баланс после конкретной операции');

            // FK
            $table->foreignId('transaction_id')->comment('ID операции')->constrained('transactions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('account_id')->comment('ID счета')->constrained('accounts')->cascadeOnUpdate()->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent()->comment('Время создания');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Время обновления');

            $table->index(['account_id', 'created_at']);
            $table->index(['transaction_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
