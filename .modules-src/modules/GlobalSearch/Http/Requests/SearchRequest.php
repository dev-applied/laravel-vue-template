<?php

declare(strict_types=1);

namespace Modules\GlobalSearch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\GlobalSearch\Support\SearchRegistry;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Two characters, not one. A single character matches most of the
            // table on every source at once, which is a table scan per source
            // for a result nobody can use.
            'q' => ['required', 'string', 'min:2', 'max:255'],

            // Restricting to what is REGISTERED, not to what this user may
            // reach: an unauthorised type is dropped later by the registry, and
            // rejecting it here would answer "that type exists" to anyone who
            // guessed a name.
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', Rule::in(app(SearchRegistry::class)->keys())],

            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }
}
