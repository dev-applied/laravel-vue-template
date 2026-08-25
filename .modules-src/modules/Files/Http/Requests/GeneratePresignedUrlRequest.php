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
            // Required, and the cap matches StoreFileRequest's `max:20480` so
            // both storage paths refuse the same file.
            //
            // Without it a presigned PUT carried NO size constraint: the signed
            // URL let the browser write an object of any size, the per-file
            // limit applied only to the `local` path, and the storage quota was
            // checked in process() — after the object had landed and been paid
            // for. Refusing here costs nothing because no bytes have moved yet.
            'file_size' => ['required', 'integer', 'min:1', 'max:'.(20480 * 1024)],
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file_size.max' => 'This file is too large. Please upload a file less than 20MB.',
        ];
    }
}
