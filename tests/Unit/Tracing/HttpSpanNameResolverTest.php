<?php

declare(strict_types=1);

namespace Tests\Unit\Tracing;

use App\Application\Tracing\Support\HttpSpanNameResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

final class HttpSpanNameResolverTest extends TestCase
{
    public function test_uses_route_uri_template_not_raw_path(): void
    {
        $request = Request::create('/api/v1/transactions/deposit', 'POST');
        $request->setRouteResolver(fn () => new Route(
            'POST',
            'api/v1/transactions/deposit',
            static fn (): string => 'ok',
        ));

        [$name, $attributes] = new HttpSpanNameResolver()->resolve($request);

        $this->assertSame('HTTP POST api/v1/transactions/deposit', $name);
        $this->assertSame('api/v1/transactions/deposit', $attributes['http.route']);
    }

    public function test_route_with_uuid_placeholder_in_template(): void
    {
        $request = Request::create('/api/v1/transactions/550e8400-e29b-41d4-a716-446655440000', 'GET');
        $request->setRouteResolver(fn () => new Route(
            'GET',
            'api/v1/transactions/{uuid}',
            static fn (): string => 'ok',
        ));

        [$name, $attributes] = new HttpSpanNameResolver()->resolve($request);

        $this->assertSame('HTTP GET api/v1/transactions/{uuid}', $name);
        $this->assertSame('api/v1/transactions/{uuid}', $attributes['http.route']);
        $this->assertStringNotContainsString('550e8400', $name);
    }

    public function test_fallback_when_route_missing(): void
    {
        $request = Request::create('/unknown', 'GET');

        [$name] = new HttpSpanNameResolver()->resolve($request);

        $this->assertSame('HTTP GET unknown', $name);
    }
}
