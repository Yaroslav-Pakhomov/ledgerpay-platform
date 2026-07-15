<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_uuid' => ['nullable', 'uuid', 'exists:customers,uuid'],
            'currency'      => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ];
    }
}
