<?php

declare(strict_types=1);

namespace Modules\SavedViews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\SavedViews\Models\SavedView;

class UpdateSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var SavedView|null $view */
        $view = $this->route('savedView');

        return [
            // `key` is deliberately absent: moving a saved view to another
            // screen would apply filters that screen does not have.
            'name' => [
                'sometimes', 'string', 'max:120',
                // Ignoring self, or renaming a view to its own name 422s.
                Rule::unique('saved_views', 'name')
                    ->where('user_id', $this->user()?->getKey())
                    ->where('key', $view?->key)
                    ->ignore($view?->getKey()),
            ],
            'payload'    => ['sometimes', 'array', 'max:64'],
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
