<?php

declare(strict_types=1);

namespace Tests\Feature\Partitioning;

use Illuminate\Console\Command;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TablePartitionsCommandTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function partitionCommands(): array
    {
        return [
            'prepare'  => ['partitions:prepare'],
            'backfill' => ['partitions:backfill'],
            'validate' => ['partitions:validate'],
            'swap'     => ['partitions:swap'],
            'cutover'  => ['partitions:cutover'],
            'ensure'   => ['partitions:ensure'],
        ];
    }

    #[DataProvider('partitionCommands')]
    public function test_unknown_table_is_rejected(string $command): void
    {
        $pending = $this->artisan($command, ['table' => 'users']);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('Допустимо')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }

    public function test_swap_requires_explicit_confirmation(): void
    {
        $pending = $this->artisan('partitions:swap', ['table' => 'audit_logs']);

        self::assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutputToContain('confirm-writes-stopped')
            ->assertExitCode(Command::FAILURE)
            ->run();
    }
}
