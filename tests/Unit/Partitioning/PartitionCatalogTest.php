<?php

declare(strict_types=1);

namespace Tests\Unit\Partitioning;

use App\Console\Partitioning\PartitionCatalog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PartitionCatalogTest extends TestCase
{
    private PartitionCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new PartitionCatalog;
    }

    public function test_catalog_lists_the_four_cutover_tables(): void
    {
        $this->assertSame(
            ['ledger_entries', 'audit_logs', 'outbox_messages', 'reconciliation_reports'],
            $this->catalog->tables(),
        );
    }

    public function test_unknown_table_is_rejected(): void
    {
        $this->assertFalse($this->catalog->knows('users'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('users');

        $table = 'users';

        /** @phpstan-ignore method.impossibleType */
        $this->catalog->assertKnown($table);
    }

    public function test_only_outbox_is_mutable(): void
    {
        $this->assertFalse($this->catalog->isImmutable('outbox_messages'));

        foreach (['ledger_entries', 'audit_logs', 'reconciliation_reports'] as $table) {
            $this->assertTrue($this->catalog->isImmutable($table), $table);
        }
    }

    public function test_parent_ddl_keeps_column_order_and_partition_key_in_primary_key(): void
    {
        foreach ($this->catalog->tables() as $table) {
            $columns = $this->catalog->columns($table);
            $ddl     = $this->catalog->parentDdl($table);

            $this->assertSame('id', $columns[0], $table);
            $this->assertSame('created_at', $columns[count($columns) - 2], $table);
            $this->assertSame('updated_at', $columns[count($columns) - 1], $table);
            $this->assertStringNotContainsString('LIKE ' . $table, $ddl);
            $this->assertStringContainsString('PRIMARY KEY (id, created_at)', $ddl);
            $this->assertStringContainsString('PARTITION BY RANGE (created_at)', $ddl);
            $this->assertStringContainsString($table . '_partitioned', $ddl);

            $offset = 0;

            foreach ($columns as $column) {
                $position = strpos($ddl, $column, $offset);

                $this->assertNotFalse($position, $table . '.' . $column);
                $offset = $position + 1;
            }

            foreach ($this->catalog->compareColumns($table) as $column) {
                $this->assertContains($column, $columns, $table . '.' . $column);
            }

            $this->assertNotSame([], $this->catalog->indexStatements($table));

            foreach ($this->catalog->indexStatements($table) as $sql) {
                $this->assertStringContainsString($table . '_partitioned', $sql);
            }
        }
    }

    public function test_uuid_tables_keep_unique_key_with_partition_column(): void
    {
        $this->assertStringContainsString(
            'UNIQUE (uuid, created_at)',
            $this->catalog->parentDdl('outbox_messages'),
        );
        $this->assertStringContainsString(
            'UNIQUE (uuid, created_at)',
            $this->catalog->parentDdl('reconciliation_reports'),
        );
    }
}
