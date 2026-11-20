<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox\Api;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInboxEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_message_id' => ['required', 'string', 'max:255'],
            'event_name'          => ['required', 'string', 'max:150'],
            'payload'             => ['required', 'array'],
        ];
    }
}
