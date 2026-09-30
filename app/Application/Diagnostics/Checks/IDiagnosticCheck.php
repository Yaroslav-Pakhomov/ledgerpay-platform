<?php

declare(strict_types=1);

namespace App\Application\Diagnostics\Checks;

use App\Application\Diagnostics\DTO\DiagnosticCheckResult;
use App\Application\Diagnostics\Services\DiagnosticsService;

/**
 * Одна атомарная проверка готовности (инфраструктура или операционный сигнал).
 *
 * Реализации вызываются из {@see DiagnosticsService}.
 */
interface IDiagnosticCheck
{
    public function run(): DiagnosticCheckResult;
}
