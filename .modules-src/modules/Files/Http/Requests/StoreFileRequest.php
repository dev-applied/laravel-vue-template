<?php

declare(strict_types=1);

namespace Modules\Files\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => 'required|file|max:20480',
            // Recorded, not resolved. This module does not own a folders
            // table — see the migration — so the value is whatever the
            // project's own folder feature supplied.
            'folder_id' => 'sometimes|nullable|integer',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.max' => 'This file is too large. Please upload a file less than 20MB.',
        ];
    }
}
