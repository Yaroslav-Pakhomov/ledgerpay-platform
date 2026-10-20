<?php

declare(strict_types=1);

namespace App\Support\Pagination;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\Cursor;

/**
 * Единый JSON-envelope для ответов с cursor pagination.
 *
 * Формирует структуру `data` + `meta` (per_page, next/prev cursor, has_more_pages)
 * для API и согласованных list endpoints; см. ADR-012.
 */
final class CursorPaginationPayload
{
    /**
     * Собирает payload из результата {@see CursorPaginator} и уже подготовленных данных.
     *
     * Курсоры кодируются через {@see Cursor::encode()};
     * на первой/последней странице соответствующий cursor в meta будет `null`.
     *
     * @param iterable<int|string, mixed> $data Элементы текущей страницы (массив, Resource::collection и т.п.)
     * @return array{
     *     data: iterable<int|string, mixed>,
     *     meta: array{
     *         per_page: int,
     *         next_cursor: string|null,
     *         prev_cursor: string|null,
     *         has_more_pages: bool,
     *     },
     * }
     */
    public static function from(CursorPaginator $paginator, iterable $data): array
    {
        return [
            'data' => $data,
            'meta' => [
                'per_page'       => $paginator->perPage(),
                'next_cursor'    => $paginator->nextCursor()?->encode(),
                'prev_cursor'    => $paginator->previousCursor()?->encode(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ];
    }
}
