<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Reconciliation\Services\ReconciliationService;
use App\Domain\Account\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;
use Throwable;

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

    /**
     * @throws Throwable
     */
    public function test_reconciliation_report_cannot_be_updated(): void
    {
        $account = Account::factory()
            ->withBalance(0)
            ->create();

        $report = app(ReconciliationService::class)->checkAccount($account);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Отчёты сверки являются неизменяемыми.');

        $report->forceFill(['difference' => 999]);
        $report->save();
    }

    /**
     * @throws Throwable
     */
    public function test_reconciliation_report_cannot_be_deleted(): void
    {
        $account = Account::factory()
            ->withBalance(0)
            ->create();

        $report = app(ReconciliationService::class)->checkAccount($account);

        $this->assertThrows(
            fn () => $report->delete(),
            LogicException::class,
            'Отчёты сверки являются неизменяемыми.',
        );
    }
}
