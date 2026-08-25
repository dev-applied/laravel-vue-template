<?php

declare(strict_types=1);

namespace Modules\SavedViews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'key'  => ['required', 'string', 'max:128'],
            'name' => [
                'required', 'string', 'max:120',
                // Caught here rather than left to the unique index: the index
                // is the backstop, but a 500 on a duplicate name is a terrible
                // way to tell someone to pick a different word.
                Rule::unique('saved_views', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->where('key', $this->input('key')),
            ],
            // The screen decides what a payload contains; this module only
            // guarantees it round-trips. The size cap is the guard — a saved
            // view is a filter set, not a place to park a dataset.
            'payload'    => ['required', 'array', 'max:64'],
            'is_default' => ['sometimes', 'boolean'],
            'is_shared'  => ['sometimes', 'boolean'],
            'position'   => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'You already have a view called that on this screen.'];
    }
}
