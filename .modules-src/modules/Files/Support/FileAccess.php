<?php

declare(strict_types=1);

namespace Modules\Files\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Files\Models\File;

/**
 * Who may see and delete a file.
 *
 * This module previously had no authorization of any kind. Every route was
 * `auth:sanctum` plus a bare route-model bind, and file ids are sequential, so
 * any authenticated user could walk `/files/download/1..N` and read every file
 * in the system — then DELETE any of them, which also unlinks the bytes.
 *
 * The reason it is a seam rather than a fixed rule: a file's audience is a
 * property of whatever it is attached to, and only the project knows that. An
 * avatar is world-readable, a signed contract is not, and both are rows in this
 * one table.
 *
 * The shipped default is {@see OwnerFileAccess}. Bind your own in
 * AppServiceProvider to follow the parent record instead:
 *
 *     $this->app->singleton(FileAccess::class, InvoiceFileAccess::class);
 */
interface FileAccess
{
    public function canView(File $file, ?Authenticatable $user): bool;

    public function canDelete(File $file, ?Authenticatable $user): bool;
}
