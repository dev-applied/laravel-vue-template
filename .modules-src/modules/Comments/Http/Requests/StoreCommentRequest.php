<?php

declare(strict_types=1);

namespace Modules\Comments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body'        => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
            // Validated against the same thread in the controller — a reply
            // pointing at another record's comment would render nowhere.
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ];
    }
}
