<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Customer\Models\Customer;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Outbox\Models\OutboxMessage;
use App\Domain\Reconciliation\Models\ReconciliationReport;
use App\Domain\Transaction\Enums\TransactionStatus;
use App\Domain\Transaction\Models\Transaction;
use App\Models\User;
use Database\Seeders\BackofficeUserSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeder_creates_realistic_dataset(): void
    {
        $this->seed(BackofficeUserSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@ledgerpay.test']);
        $this->assertDatabaseHas('users', ['email' => 'alice@ledgerpay.test']);
        $this->assertDatabaseHas('customers', ['email' => 'finance@acme.test']);

        $this->assertGreaterThanOrEqual(4, Customer::query()->count());
        $this->assertGreaterThanOrEqual(5, User::query()->count());
        $this->assertGreaterThanOrEqual(7, Transaction::query()->count());
        $this->assertGreaterThanOrEqual(9, LedgerEntry::query()->count());
        $this->assertGreaterThanOrEqual(2, AuditLog::query()->count());
        $this->assertGreaterThanOrEqual(6, OutboxMessage::query()->count());
        $this->assertGreaterThanOrEqual(5, ReconciliationReport::query()->count());

        $this->assertTrue(
            Transaction::query()->where('status', TransactionStatus::Failed)->exists()
        );
    }
}
