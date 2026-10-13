<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Transaction\Jobs\ProcessTransactionJob;
use App\Application\Transaction\Routing\TransactionQueueRouter;
use App\Domain\Account\Models\Account;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Concerns\CreatesApiFixtures;
use Tests\TestCase;

/**
 * Feature-тесты маршрутизации {@see ProcessTransactionJob} в account-sharded очереди.
 *
 * Проверяют {@see TransactionQueueRouter} и интеграцию с {@see TransactionService}
 * при создании deposit через HTTP (без фактической обработки worker'ом):
 *
 * - стабильный shard (сегмент) для одного routing account (deposit → target, withdrawal → source);
 * - dispatch job на очередь `transactions-account-XX`, вычисленную router'ом;
 * - полный список имён shard-очередей из `config('ledgerpay.queues.transaction_shards')`.
 *
 * {@see TransactionProcessingTest} покрывает общий async-контракт (pending + dispatch);
 * здесь — только routing и имя очереди.
 *
 * Queue::fake() изолирует проверку `onQueue` от Redis/Horizon.
 */
final class TransactionQueueRoutingTest extends TestCase
{
    use CreatesApiFixtures;
    use RefreshDatabase;

    /**
     * Deposit и withdrawal по одному счёту получают одно и то же имя очереди.
     *
     * Routing key: target для deposit, source для withdrawal — оба указывают на один account id.
     */
    public function test_router_returns_stable_queue_for_same_account(): void
    {
        config()->set('ledgerpay.queues.transaction_shards', 16);

        $account = Account::factory()->create();

        $first = Transaction::query()->create([
            'type'                   => TransactionType::Deposit,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => null,
            'target_account_id'      => $account->id,
            'amount'                 => 1000,
            'currency'               => 'USD',
            'idempotency_key'        => 'queue-routing-001',
            'idempotency_expires_at' => now()->addDay(),
        ]);

        $second = Transaction::query()->create([
            'type'                   => TransactionType::Withdrawal,
            'status'                 => TransactionStatus::Pending,
            'source_account_id'      => $account->id,
            'target_account_id'      => null,
            'amount'                 => 1000,
            'currency'               => 'USD',
            'idempotency_key'        => 'queue-routing-002',
            'idempotency_expires_at' => now()->addDay(),
        ]);

        $router = app(TransactionQueueRouter::class);

        $this->assertSame(
            $router->queueFor($first),
            $router->queueFor($second),
        );
    }

    /**
     * POST deposit ставит {@see ProcessTransactionJob} на сегмент, который вернул router для созданной транзакции.
     */
    public function test_transaction_creation_dispatches_job_to_sharded_queue(): void
    {
        Queue::fake();

        config()->set('ledgerpay.queues.transaction_shards', 16);

        $account = Account::factory()->currency('USD')->withBalance(0)->create();
        $this->actingAsCustomerFor($account);

        $this->postJson('/api/transactions/deposit', [
            'target_account_uuid' => $account->uuid,
            'amount'              => 1000,
            'currency'            => 'USD',
        ], [
            'Idempotency-Key' => 'queue-routing-api-001',
        ])->assertCreated();

        $transaction = Transaction::query()->firstOrFail();

        $expectedQueue = app(TransactionQueueRouter::class)->queueFor($transaction);

        Queue::assertPushedOn($expectedQueue, ProcessTransactionJob::class);
    }

    /**
     * {@see TransactionQueueRouter::allTransactionQueues()} возвращает `00` … `shards-1` при shards=4.
     */
    public function test_router_exposes_all_shard_queues(): void
    {
        config()->set('ledgerpay.queues.transaction_shards', 4);

        $queues = app(TransactionQueueRouter::class)->allTransactionQueues();

        $this->assertSame([
            'transactions-account-00',
            'transactions-account-01',
            'transactions-account-02',
            'transactions-account-03',
        ], $queues);
    }
}
