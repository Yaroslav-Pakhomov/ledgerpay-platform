<?php

declare(strict_types=1);

namespace App\Application\Tracing\Support;

use App\Http\Middleware\TraceHttpRequestMiddleware;
use Illuminate\Http\Request;

/**
 * Имя серверного HTTP-сегмента и атрибуты маршрута для OpenTelemetry (ADR-016).
 *
 * При сопоставленном {@see Request::route()} — URI-шаблон (`{uuid}` вместо конкретного id),
 * иначе — {@see Request::path()}. Низкая кардинальность имён в Jaeger.
 * Вызывается из {@see TraceHttpRequestMiddleware}.
 */
final class HttpSpanNameResolver
{
    /**
     * Возвращает имя сегмента и опциональные атрибуты (`http.route`, `laravel.route.name`).
     * Без сопоставленного маршрута имя строится через {@see SpanNames::httpRequest()}.
     *
     * @return array{0: non-empty-string, 1: array<string, string>} `[имя, атрибуты]`; без маршрута атрибуты пустые
     */
    public function resolve(Request $request): array
    {
        /** @var non-empty-string $method */
        $method = $request->method() !== '' ? $request->method() : 'GET';

        $route    = $request->route();
        $template = $route?->uri();

        if (is_string($template) && $template !== '') {
            $name = 'HTTP ' . $method . ' ' . $template;

            $attributes = [
                'http.route' => $template,
            ];

            $routeName = $route->getName();

            if (is_string($routeName) && $routeName !== '') {
                $attributes['laravel.route.name'] = $routeName;
            }

            return [$name, $attributes];
        }

        /** @var non-empty-string $path */
        $path = ($p = $request->path()) !== '' ? $p : '/';

        return [SpanNames::httpRequest($method, $path), []];
    }
}
