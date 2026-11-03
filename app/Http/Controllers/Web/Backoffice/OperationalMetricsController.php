<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backoffice;

use App\Application\Metrics\Services\OperationalMetricsPresenterService;
use App\Http\Controllers\Controller;
use Inertia\Response;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * GET /backoffice/operational-metrics — операционные метрики из Redis-снимка (не опрос /metrics).
 */
final class OperationalMetricsController extends Controller
{
    /**
     * @throws InvalidArgumentException
     */
    public function __invoke(OperationalMetricsPresenterService $presenter): Response
    {
        return inertia('Backoffice/OperationalMetrics', $presenter->present());
    }
}
