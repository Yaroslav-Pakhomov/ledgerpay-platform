<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Reconciliation\Services\ReconciliationService;
use App\Domain\Reconciliation\Enums\ReconciliationStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Artisan-команда: сверка реестра vs баланса счёта.
 *
 * Вызывает {@see ReconciliationService::checkAllAccounts()}.
 * Код выхода FAILURE при расхождениях — удобно для планировщика и алертов.
 *
 * Планируется в {@see routes/console.php} ежечасно с withoutOverlapping().
 */
#[Description('Сверка балансов счетов с реестром проводок.')]
#[Signature('reconciliation:run {--limit=1000}')]
final class RunReconciliationCommand extends Command
{
    /**
     * @return int Command::SUCCESS|Command::FAILURE
     *
     * @throws Throwable
     */
    public function handle(ReconciliationService $service): int
    {
        $limit = (int) $this->option('limit');

        $reports = $service->checkAllAccounts($limit);

        $matched = $reports
            ->filter(fn ($report) => $report->status === ReconciliationStatus::Matched)
            ->count();

        $mismatched = $reports
            ->filter(fn ($report) => $report->status === ReconciliationStatus::Mismatched)
            ->count();

        $this->info("Сверка завершена. Совпадает: {$matched}. Расхождение: {$mismatched}.");

        return $mismatched > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
