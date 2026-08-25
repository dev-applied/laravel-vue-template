<?php

declare(strict_types=1);

namespace Modules\Exports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Exports\Support\ExportRegistry;

class StoreExportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The registry IS the allow-list — an unregistered key is a
            // validation error, never an attempt to resolve something arbitrary.
            'source'  => ['required', 'string', Rule::in(app(ExportRegistry::class)->keys())],
            'format'  => ['sometimes', Rule::in(['csv', 'xlsx'])],
            'filters' => ['sometimes', 'array'],
        ];
    }
}
