<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Формирует единый формат ошибок
 *
 * Ответ имеет специальный Content-Type:
 * application/problem+json
 * Клиенту больше не нужно разбирать разные форматы ошибок в зависимости от того, где произошла проблема.
 *
 * Обрабатываются следующие исключения:
 *  - Ошибки валидации — 422;
 *  - Ресурс не найден — 404;
 *  - Нарушение бизнес-правила — 409;
 *  - Пользователь не авторизован — 401;
 *  - Остальные исключения - обработчик Throwable.
 */
final class ProblemDetails
{
    public static function make(
        Request $request,
        string $title,
        string $detail,
        int $status,
        string $type = 'about:blank',
        ?array $errors = null,
    ): JsonResponse {
        $payload = [
            'type'       => $type,
            'title'      => $title,
            'status'     => $status,
            'detail'     => $detail,
            'instance'   => $request->path(),
            'request_id' => $request->headers->get('X-Request-Id'),
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json(
            data: $payload,
            status: $status,
            headers: [
                'Content-Type' => 'application/problem+json',
            ],
        );
    }

    public static function validation(
        Request $request,
        array $errors,
    ): JsonResponse {
        return self::make(
            request: $request,
            title: 'Validation failed',
            detail: 'The request payload contains invalid data.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            type: 'https://ledgerpay.local/problems/validation-failed',
            errors: $errors,
        );
    }
}
