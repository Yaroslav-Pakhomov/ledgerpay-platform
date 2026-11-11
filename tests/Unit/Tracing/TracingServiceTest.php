<?php

declare(strict_types=1);

namespace Tests\Unit\Tracing;

use App\Application\Tracing\Services\TracingService;
use Illuminate\Support\Facades\Context;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class TracingServiceTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function test_context_is_restored_after_span(): void
    {
        $tracing = app(TracingService::class);

        Context::add('trace_id', 'original');

        $tracing->withinSpan(
            name: 'test.operation',
            callback: function () use ($tracing): void {
                $this->assertNotNull($tracing->currentTraceId());
            },
        );

        $this->assertSame('original', Context::get('trace_id'));

        Context::forget('trace_id');
    }

    public function test_trace_context_is_restored_after_exception(): void
    {
        $tracing = app(TracingService::class);

        Context::add('trace_id', 'original');

        try {
            $tracing->withinSpan(
                name: 'test.failed-operation',
                callback: static function (): void {
                    throw new RuntimeException('Test error');
                },
            );
        } catch (Throwable) {
        }

        $this->assertSame('original', Context::get('trace_id'));

        Context::forget('trace_id');
    }

    public function test_invalid_traceparent_is_rejected(): void
    {
        $tracing = app(TracingService::class);

        $this->assertFalse(
            $tracing->isValidRemoteContext(['traceparent' => 'invalid'])
        );
    }
}
