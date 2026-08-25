<?php

declare(strict_types=1);

namespace Modules\Files\Support;

use Illuminate\Http\UploadedFile;

/**
 * A seam for refusing a file before it is kept.
 *
 * The module does not ship a scanner and should not: a real one means ClamAV,
 * an antivirus API, or a bucket-level scanning product, and which of those a
 * project can run is not something a vendored module can decide. What it can do
 * is give that decision one place to live, so "we don't scan uploads" becomes
 * "here is where scanning goes" — the same move OtpChannel, TagPoolScope,
 * SavedViewScope and FileAccess already make.
 *
 *   $this->app->bind(FileScanner::class, ClamAvScanner::class);
 *
 * Called on BOTH storage paths, but at different moments — see refuse().
 */
interface FileScanner
{
    /**
     * Null when the file is safe to keep; a short, user-safe reason when it is
     * not. The reason is shown to whoever uploaded it, so it should say "this
     * file was refused", never which signature matched or which engine ran.
     *
     * WHEN THIS RUNS:
     *  - `storage=local` — before the bytes are written anywhere.
     *  - `storage=s3-presigned` — the browser PUTs straight to the bucket, so
     *    the object already exists by the time the app can see it. The scan
     *    happens in `process()`, against the copy pulled down for variant
     *    generation, and a refusal deletes the object and the row. That is
     *    later than ideal and it is the honest limit of the design; a project
     *    that needs the bytes never to land should scan at the bucket instead.
     */
    public function refuse(UploadedFile $file): ?string;
}
