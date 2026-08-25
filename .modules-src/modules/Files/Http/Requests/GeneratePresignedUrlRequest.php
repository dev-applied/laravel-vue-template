<?php

declare(strict_types=1);

namespace Modules\Files\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePresignedUrlRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file_name' => 'required|string',
            'folder_id' => 'sometimes|nullable|integer',
            'file_type' => 'required|string',
            // An allow-list, not a free string.
            //
            // This value became the S3 KEY PREFIX of a presigned PUT, so the
            // caller chose where in the bucket their signed URL could write —
            // another feature's prefix, another tenant's, or anywhere `..`
            // took them, since an S3 key is a literal string and nothing
            // resolves the traversal away. Nothing in the module's own
            // frontend has ever sent this field.
            //
            // Projects that need more roots publish config/files.php with
            // `upload_prefixes` — see the module README.
            'path' => ['sometimes', 'nullable', 'string', Rule::in(config('files.upload_prefixes', ['uploads']))],
        ];
    }
}
