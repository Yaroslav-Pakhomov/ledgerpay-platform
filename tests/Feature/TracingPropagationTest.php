<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class TracingPropagationTest extends TestCase
{
    public function test_http_response_contains_trace_id(): void
    {
        $response = $this->get('/api/v1/health/live');

        $response->assertHeader('X-Trace-Id');

        $traceId = $response->headers->get('X-Trace-Id');

        $this->assertIsString($traceId);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $traceId);
    }

    public function test_valid_traceparent_is_accepted(): void
    {
        $traceId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $response = $this->get('/api/v1/health/live', [
            'traceparent' => '00-' . $traceId . '-1111111111111111-01',
        ]);

        $response->assertOk();
        $response->assertHeader('X-Trace-Id', $traceId);
    }

    public function test_invalid_traceparent_creates_new_trace(): void
    {
        $response = $this->get('/api/v1/health/live', [
            'traceparent' => 'invalid',
        ]);

        $response->assertHeader('X-Trace-Id');

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{32}$/',
            (string) $response->headers->get('X-Trace-Id')
        );
    }
}
