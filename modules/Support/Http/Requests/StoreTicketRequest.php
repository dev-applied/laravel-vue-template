<?php

declare(strict_types=1);

namespace Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:125'],
            'email'   => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:5000'],
            // Honeypot: a field no human sees. Bots fill every input they find,
            // so anything here means "not a person". Must be ABSENT or empty.
            'website' => ['nullable', 'prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['website.prohibited' => 'Your submission could not be accepted.'];
    }
}
