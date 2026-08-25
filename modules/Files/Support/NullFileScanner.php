<?php

declare(strict_types=1);

namespace Modules\Files\Support;

use Illuminate\Http\UploadedFile;

/**
 * The shipped default: nothing is refused.
 *
 * Permissive because the alternative is a module that rejects every upload on
 * install. Bind a real scanner the moment the project accepts files from anyone
 * it does not already trust.
 */
class NullFileScanner implements FileScanner
{
    public function refuse(UploadedFile $file): ?string
    {
        return null;
    }
}
