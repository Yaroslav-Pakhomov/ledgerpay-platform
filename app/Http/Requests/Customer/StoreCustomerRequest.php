<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * В реальном fintech API email будет частью KYC/CRM-процесса.
     */
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:customers,email'],
        ];
    }
}
