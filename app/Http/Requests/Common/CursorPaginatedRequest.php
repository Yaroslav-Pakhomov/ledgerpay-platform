<?php

declare(strict_types=1);

namespace App\Http\Requests\Common;

use App\Http\Requests\Concerns\UsesCursorPagination;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос списка с cursor-пагинацией.
 *
 * Принимает query-параметры `cursor` и `limit`. Размер страницы считает {@see UsesCursorPagination::cursorLimit()}.
 */
final class CursorPaginatedRequest extends FormRequest
{
    use UsesCursorPagination;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'limit'  => ['nullable', 'integer', 'min:1'],
        ];
    }
}
