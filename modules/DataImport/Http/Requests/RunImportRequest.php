<?php

declare(strict_types=1);

namespace Modules\DataImport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunImportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mapping'   => ['required', 'array', 'min:1'],
            'mapping.*' => ['required', 'string'],
        ];
    }
}
