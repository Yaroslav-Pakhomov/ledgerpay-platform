<?php

declare(strict_types=1);

namespace Tests\Feature\Partitioning;

use App\Console\Partitioning\PartitionCutover;
use App\Domain\Account\Models\Account;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Outbox\Enums\OutboxStatus;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Transaction\Enums\TransactionType;
use App\Domain\Transaction\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Cutover партиций на PostgreSQL.
 *
 * RefreshDatabase держит тест в транзакции, поэтому CREATE/RENAME откатываются.
 */
final class PartitionCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Cutover партиций требует PostgreSQL (DB_CONNECTION=pgsql).');
        }
    }

    public function test_backfill_keeps_rows_inserted_before_prepare_and_trigger_copies_later_inserts(): void
    {
        $this->audit('before-1');
        $this->audit('before-2');

        $this->runPartitions('partitions:prepare', [
            'table'    => 'audit_logs',
            '--months' => 1,
        ]);

        $this->assertSame('p', $this->relationKind('audit_logs_partitioned'));
        $this->assertSame('r', $this->relationKind('audit_logs'));
        $this->assertStringContainsString('nextval', (string) $this->idDefault('audit_logs_partitioned'));

        $copied = $this->audit('after-prepare');

        $this->assertSame(
            [$copied->id],
            $this->ids('audit_logs_partitioned'),
        );

        $this->runPartitions('partitions:backfill', [
            'table'   => 'audit_logs',
            '--batch' => 1,
        ]);
        $this->runPartitions('partitions:validate', ['table' => 'audit_logs']);

        $this->assertSame($this->ids('audit_logs'), $this->ids('audit_logs_partitioned'));
        $this->assertSame(
            (int) DB::table('audit_logs')->max('id'),
            (int) DB::table('partition_cutover_progress')->where('table_name', 'audit_logs')->value('last_source_id'),
        );
        $this->assertNotNull(
            DB::table('partition_cutover_progress')->where('table_name', 'audit_logs')->value('validated_at'),
        );

        $this->runPartitions('partitions:prepare', [
            'table'    => 'audit_logs',
            '--months' => 1,
        ]);
    }

    /**
     * @throws BindingResolutionException
     */
    public function test_cutover_command_validates_without_renaming_the_table(): void
    {
        $this->audit('cutover');

        $pending = $this->artisan('partitions:cutover', [
            'table'    => 'audit_logs',
            '--months' => 1,
            '--batch'  => 10,
        ]);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('prepare')
            ->expectsOutputToContain('backfill')
            ->expectsOutputToContain('validate')
            ->expectsOutputToContain('partitions:swap')
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $this->assertSame('r', $this->relationKind('audit_logs'));
        $this->assertNull($this->relationKind('audit_logs_legacy'));
    }

    public function test_ensure_rejects_a_table_that_is_not_partitioned_yet(): void
    {
        $pending = $this->artisan('partitions:ensure', ['table' => 'audit_logs']);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('ещё не партиционирована')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_outbox_update_is_copied_to_shadow_and_direct_shadow_edit_fails_validate(): void
    {
        $this->runPartitions('partitions:prepare', [
            'table'    => 'outbox_messages',
            '--months' => 1,
        ]);

        $message = OutboxMessage::query()->create([
            'event_name'     => 'transaction.created',
            'aggregate_type' => Transaction::class,
            'aggregate_id'   => 1,
            'payload'        => ['id' => 1],
            'status'         => OutboxStatus::Pending,
        ]);

        $message->update([
            'status'   => OutboxStatus::Published,
            'attempts' => 2,
        ]);

        $this->assertSame(
            'published',
            DB::table('outbox_messages_partitioned')->where('id', $message->id)->value('status'),
        );
        $this->assertSame(
            2,
            (int) DB::table('outbox_messages_partitioned')->where('id', $message->id)->value('attempts'),
        );

        $this->runPartitions('partitions:backfill', [
            'table'   => 'outbox_messages',
            '--batch' => 10,
        ]);
        $this->runPartitions('partitions:validate', ['table' => 'outbox_messages']);

        DB::table('outbox_messages_partitioned')
            ->where('id', $message->id)
            ->update(['attempts' => 99]);

        $pending = $this->artisan('partitions:validate', ['table' => 'outbox_messages']);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('Расхождение данных')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_outbox_rejects_a_change_of_the_partition_key(): void
    {
        $this->runPartitions('partitions:prepare', [
            'table'    => 'outbox_messages',
            '--months' => 1,
        ]);

        $message = OutboxMessage::query()->create([
            'event_name'     => 'transaction.created',
            'aggregate_type' => Transaction::class,
            'aggregate_id'   => 1,
            'payload'        => ['id' => 1],
            'status'         => OutboxStatus::Pending,
        ]);

        $this->expectExceptionMessage('partition key');

        DB::table('outbox_messages')
            ->where('id', $message->id)
            ->update(['created_at' => now()->subMonth()]);
    }

    public function test_ledger_validate_checks_the_amount_sum(): void
    {
        $account     = Account::factory()->withBalance(10_000)->create();
        $transaction = Transaction::factory()->create([
            'type'              => TransactionType::Deposit,
            'target_account_id' => $account->id,
            'source_account_id' => null,
        ]);

        foreach ([4_000, 6_000] as $amount) {
            LedgerEntry::query()->create([
                'transaction_id' => $transaction->id,
                'account_id'     => $account->id,
                'direction'      => LedgerDirection::Credit,
                'amount'         => $amount,
                'currency'       => 'USD',
                'balance_after'  => $amount,
            ]);
        }

        $this->runPartitions('partitions:cutover', [
            'table'    => 'ledger_entries',
            '--months' => 1,
            '--batch'  => 10,
        ]);

        DB::table('ledger_entries_partitioned')->insert([
            'id'             => 9_000_000,
            'direction'      => 'credit',
            'currency'       => 'USD',
            'amount'         => 1,
            'balance_after'  => 1,
            'transaction_id' => $transaction->id,
            'account_id'     => $account->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $pending = $this->artisan('partitions:validate', ['table' => 'ledger_entries']);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('Сумма amount не сходится')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_swap_rewires_the_sequence_and_ensure_creates_a_future_month(): void
    {
        $existing = $this->audit('before-swap');

        $this->runPartitions('partitions:cutover', [
            'table'    => 'audit_logs',
            '--months' => 1,
            '--batch'  => 10,
        ]);
        $this->runPartitions('partitions:swap', [
            'table'                    => 'audit_logs',
            '--confirm-writes-stopped' => true,
        ]);

        $this->assertSame('p', $this->relationKind('audit_logs'));
        $this->assertSame('r', $this->relationKind('audit_logs_legacy'));
        $this->assertNull($this->relationKind('audit_logs_partitioned'));

        $created = $this->audit('after-swap');

        $this->assertGreaterThan($existing->id, $created->id);
        $this->assertSame(1, DB::table('audit_logs')->where('id', $created->id)->count());

        $pending = $this->artisan('partitions:ensure', [
            'table'    => 'audit_logs',
            '--months' => 4,
        ]);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('Создана')
            ->assertExitCode(Command::SUCCESS)
            ->run();

        $pending = $this->artisan('partitions:cutover', ['table' => 'audit_logs']);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('уже партиционирована')
            ->assertExitCode(Command::SUCCESS)
            ->run();
    }

    public function test_validate_rejects_a_row_that_exists_only_in_shadow(): void
    {
        $this->runPartitions('partitions:prepare', [
            'table'    => 'audit_logs',
            '--months' => 1,
        ]);

        DB::table('audit_logs_partitioned')->insert([
            'id'         => 9_000_000,
            'action'     => AuditAction::UserLoggedIn->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pending = $this->artisan('partitions:validate', [
            'table' => 'audit_logs',
        ]);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('extra=9000000')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_backfill_rejects_non_partitioned_shadow(): void
    {
        DB::statement(
            'CREATE TABLE audit_logs_partitioned (id bigint NOT NULL)',
        );

        $pending = $this->artisan('partitions:backfill', [
            'table' => 'audit_logs',
        ]);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('должна быть partitioned table')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    private function audit(string $requestId): AuditLog
    {
        return AuditLog::query()->create([
            'action'     => AuditAction::UserLoggedIn,
            'request_id' => $requestId,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function runPartitions(string $command, array $parameters): void
    {
        $pending = $this->artisan($command, $parameters);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending->assertExitCode(Command::SUCCESS)->run();
    }

    /**
     * @throws BindingResolutionException
     */
    private function relationKind(string $name): ?string
    {
        return $this->app->make(PartitionCutover::class)->relationKind($name);
    }

    private function idDefault(string $table): ?string
    {
        $default = DB::scalar(
            <<<'SQL'
            SELECT column_default
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = ?
              AND column_name = 'id'
            SQL,
            [$table],
        );

        return is_string($default) ? $default : null;
    }

    /**
     * @return list<int>
     */
    private function ids(string $table): array
    {
        return array_values(array_map(intval(...), DB::table($table)->orderBy('id')->pluck('id')->all()));
    }
}
