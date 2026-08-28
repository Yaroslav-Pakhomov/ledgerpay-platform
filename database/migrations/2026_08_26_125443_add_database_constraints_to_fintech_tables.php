<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Миграция по добавлению условий корректности данных

        // region Счёт
        // Ограничение на отрицательный баланс счёта
        DB::statement('
            ALTER TABLE accounts
            ADD CONSTRAINT accounts_balance_non_negative
            CHECK (balance >= 0)
        ');

        // Ограничение на неверный формат валюты счёта
        DB::statement('
            ALTER TABLE accounts
            ADD CONSTRAINT accounts_currency_uppercase
            CHECK (currency = upper(currency) AND char_length(currency) = 3)
        ');
        // endregion Счёт

        // region Операция
        // Ограничение на нулевые/отрицательные суммы перевода
        DB::statement('
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_amount_positive
            CHECK (amount > 0)
        ');

        // Ограничение на неверный формат валюты операции
        DB::statement('
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_currency_uppercase
            CHECK (currency = upper(currency) AND char_length(currency) = 3)
        ');

        // Ограничение на существование счёта для текущего типа операции ввод/вывод/перевод. В случае перевод счета не равны
        DB::statement("
            ALTER TABLE transactions
            ADD CONSTRAINT transactions_valid_account_shape
            CHECK (
                (
                    type = 'deposit'
                    AND source_account_id IS NULL
                    AND target_account_id IS NOT NULL
                )
                OR
                (
                    type = 'withdrawal'
                    AND source_account_id IS NOT NULL
                    AND target_account_id IS NULL
                )
                OR
                (
                    type = 'transfer'
                    AND source_account_id IS NOT NULL
                    AND target_account_id IS NOT NULL
                    AND source_account_id <> target_account_id
                )
            )
        ");
        // endregion Операция

        // region Бухгалтерские записи
        // Ограничение на нулевые/отрицательные суммы перевода
        DB::statement('
            ALTER TABLE ledger_entries
            ADD CONSTRAINT ledger_entries_amount_positive
            CHECK (amount > 0)
        ');

        // Ограничение на неверный формат валюты операции
        DB::statement('
            ALTER TABLE ledger_entries
            ADD CONSTRAINT ledger_entries_currency_uppercase
            CHECK (currency = upper(currency) AND char_length(currency) = 3)
        ');

        // Ограничение на тип операции, допустимые - ввод/вывод
        DB::statement("
            ALTER TABLE ledger_entries
            ADD CONSTRAINT ledger_entries_direction_valid
            CHECK (direction IN ('debit', 'credit'))
        ");
        // endregion Бухгалтерские записи
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_direction_valid');
        DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_currency_uppercase');
        DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_amount_positive');

        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_valid_account_shape');
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_currency_uppercase');
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_amount_positive');

        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_currency_uppercase');
        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_balance_non_negative');
    }
};
