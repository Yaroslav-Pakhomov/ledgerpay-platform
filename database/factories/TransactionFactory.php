<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Test Factory для агрегата Transaction.
 *
 * Transaction описывает намерение движения средств (deposit/withdrawal/transfer).
 * Factory использует lazy relations для счетов — Eloquent создаст связанные
 * агрегаты Account только при materialization, без eager side effects в definition().
 *
 * @extends Factory<Transaction>
 */
final class TransactionFactory extends Factory
{
    #[\Override]
    protected $model = Transaction::class;

    /**
     * Базовое состояние: pending transfer между двумя счетами.
     */
    public function definition(): array
    {
        return [
            'type'              => TransactionType::Transfer,
            'status'            => TransactionStatus::Pending,
            'source_account_id' => Account::factory(),
            'target_account_id' => Account::factory(),
            'amount'            => 1000,
            'currency'          => 'USD',
            'idempotency_key'   => (string) Str::uuid(),
        ];
    }

    /**
     * Состояние для сценариев retry и обработки ошибок worker'а.
     */
    public function failed(): self
    {
        return $this->state([
            'status'         => TransactionStatus::Failed,
            'failure_reason' => 'Previous failure.',
        ]);
    }
}
