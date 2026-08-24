<?php

declare(strict_types=1);

namespace Modules\Example\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExampleNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:255'],
        ];
    }
}
