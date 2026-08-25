<?php

declare(strict_types=1);

namespace Modules\DataImport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\DataImport\Support\ImportRegistry;

class StoreImportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', Rule::in(app(ImportRegistry::class)->keys())],
            'file'   => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ];
    }
}
