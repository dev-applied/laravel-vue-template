<?php

declare(strict_types=1);

namespace Modules\Files\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Modules\Files\Models\File;

/**
 * The default: you get your own files, and nothing else.
 *
 * Deliberately the conservative reading. It keeps the case that always works —
 * "I uploaded it, I can see it" — with no project configuration, and denies
 * everything else rather than guessing. A file whose uploader is unknown
 * (`created_by` null, e.g. written by a queue job or a seeder) is denied to
 * everyone rather than granted to everyone; that is the direction to be wrong in.
 *
 * Most projects will outgrow this the moment a file is shared, which is the
 * point at which they should bind their own {@see FileAccess} — the denial is
 * logged with that instruction so the first person to hit it knows why.
 */
class OwnerFileAccess implements FileAccess
{
    public function canView(File $file, ?Authenticatable $user): bool
    {
        return $this->owns($file, $user);
    }

    public function canDelete(File $file, ?Authenticatable $user): bool
    {
        return $this->owns($file, $user);
    }

    protected function owns(File $file, ?Authenticatable $user): bool
    {
        // getCreatedByColumn(), not a literal: the column name comes from
        // config/who-did-it.php (`created_by_id` by default) and a model may
        // override it. Hardcoding 'created_by' reads null on every row, which
        // denies everything — including the owner.
        $owner = $file->getAttribute($file->getCreatedByColumn());

        if ($user === null || $owner === null) {
            $this->explain($file);

            return false;
        }

        if ((string) $owner === (string) $user->getAuthIdentifier()) {
            return true;
        }

        $this->explain($file);

        return false;
    }

    /** Logged once per request, not once per file — a gallery would drown the log. */
    protected function explain(File $file): void
    {
        static $explained = false;

        if ($explained) {
            return;
        }

        $explained = true;

        Log::info(
            'modules/Files: denied by the default OwnerFileAccess, which allows a file only to its '
            .'uploader. If files in this project are shared, bind your own '
            .FileAccess::class.' in AppServiceProvider — see modules/Files/README.md.',
            ['file_id' => $file->getKey()]
        );
    }
}
