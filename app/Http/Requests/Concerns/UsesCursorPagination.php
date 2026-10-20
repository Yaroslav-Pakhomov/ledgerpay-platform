<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Общая логика page size для cursor pagination в Form Request.
 *
 * Читает query-параметр `limit`, подставляет {@see config()} `ledgerpay.pagination.default_limit`
 * при отсутствии значения и ограничивает результат диапазоном [1, `ledgerpay.pagination.max_limit`].
 *
 * @mixin FormRequest
 */
trait UsesCursorPagination
{
    /**
     * Эффективный размер страницы для {@see Builder::cursorPaginate()}.
     *
     * Невалидные или отсутствующие значения `limit` приводятся к default; значения ниже 1 — к 1,
     * выше max — к `ledgerpay.pagination.max_limit`.
     */
    public function cursorLimit(): int
    {
        $default = (int) config('ledgerpay.pagination.default_limit', 50);
        $max     = (int) config('ledgerpay.pagination.max_limit', 100);

        $limit = (int) $this->integer('limit', $default);

        return min(max($limit, 1), $max);
    }
}
