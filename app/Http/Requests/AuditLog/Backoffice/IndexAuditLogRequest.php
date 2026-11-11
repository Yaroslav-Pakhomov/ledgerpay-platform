<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLog\Backoffice;

use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // доступ — middleware `backoffice`
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'action'     => $this->filled('action') ? $this->string('action')->toString() : null,
            'request_id' => $this->filled('request_id') ? $this->string('request_id')->toString() : null,
            'trace_id'   => $this->filled('trace_id') ? $this->string('trace_id')->toString() : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'action'     => ['nullable', 'string', 'max:255'],
            'request_id' => ['nullable', 'string', 'max:255'],
            'trace_id'   => ['nullable', 'string', 'size:32', 'regex:/^[a-f0-9]{32}$/'],
        ];
    }
}
