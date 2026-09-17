<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Reconciliation\Services\ReconciliationService;
use App\Domain\Account\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Feature-тесты доменного инварианта immutable reconciliation reports.
 *
 * ReconciliationReport — append-only снимок результата сверки.
 * Доменная модель запрещает update/delete, чтобы сохранить
 * доверие к истории расследований в бэк-офисе.
 */
final class ReconciliationImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_report_cannot_be_updated(): void
    {
        $account = Account::factory()
            ->withBalance(0)
            ->create();

        $report = app(ReconciliationService::class)->checkAccount($account);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Отчёты сверки являются неизменяемыми.');

        $report->update([
            'difference' => 999,
        ]);
    }

    public function test_reconciliation_report_cannot_be_deleted(): void
    {
        $account = Account::factory()
            ->withBalance(0)
            ->create();

        $report = app(ReconciliationService::class)->checkAccount($account);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Отчёты сверки являются неизменяемыми.');

        $report->delete();
    }
}
