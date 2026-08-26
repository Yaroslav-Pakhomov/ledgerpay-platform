<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'min:2', 'max:255'],
            'email' => [
                'required',
                app()->environment('testing') ? 'email' : 'email:rfc,dns',
                'max:255',
                'unique:users,email',
                'unique:customers,email',
            ],
            'password' => [
                'required',
                'string',
                app()->environment('testing')
                    ? Password::min(12)->mixedCase()->numbers()->symbols()
                    : Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ];
    }
}
