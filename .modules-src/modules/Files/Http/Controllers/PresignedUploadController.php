<?php

declare(strict_types=1);

namespace Modules\Files\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Files\Http\Requests\GeneratePresignedUrlRequest;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Models\File;
use Modules\Files\Support\FileAccess;
use Modules\Files\Support\FileScanner;
use Modules\Files\Support\StorageQuota;

/**
 * The `storage=s3-presigned` half of the module: the browser PUTs bytes
 * straight to S3 so uploads never pass through PHP, then variant generation
 * happens after the object lands.
 *
 * Dropped entirely when the module is installed with `storage=local`.
 */
class PresignedUploadController extends Controller
{
    /**
     * Reserve a File row and hand back a presigned PUT the browser can upload to.
     * The row starts `processed = false`; `process()` flips it once variants exist.
     *
     * @throws AppException
     */
    public function generate(GeneratePresignedUrlRequest $request, StorageQuota $quota): JsonResponse
    {
        $sizeBytes = (int) $request->input('file_size');

        // Ahead of the disk check on purpose. The quota was only ever consulted
        // in process(), which runs AFTER the object is in the bucket — so an
        // over-quota upload was refused having already been stored and charged
        // for. Refusing here costs nothing, depends on no infrastructure, and
        // happens before a File row is reserved. `files.size` is kilobytes at
        // /1000, matching File::upload, so the incoming figure converts the same.
        $refusal = $quota->refuse($request->user()?->getKey(), (int) ($sizeBytes / 1000));

        if ($refusal !== null) {
            // 413, the same as the direct path in FileController. The condition
            // is identical, so the status has to be — a client cannot handle
            // "over quota" uniformly if it arrives as 422 here and 413 there.
            throw new AppException($refusal, 413);
        }

        $disk = Storage::disk();

        // providesTemporaryUrls() is NOT sufficient: a local disk with serving
        // enabled reports true, then getClient() fatals because there is no
        // underlying S3 client. Check for the client itself.
        if (! method_exists($disk, 'getClient')) {
            throw new AppException('The configured storage disk does not support presigned uploads.', 400);
        }

        $originalName = (string) $request->input('file_name');
        $fileType     = (string) $request->input('file_type');
        $ext          = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        // Same collision as File::uniqueFileName had — see its docblock.
        // time() is one-second granular and the directory is derived from this
        // same string, so two same-named uploads in one second shared a path.
        $fileName  = mb_strtolower(str_replace(".$ext", '', $originalName).'_'.time().'_'.Str::random(8).".$ext");
        $directory = mb_rtrim(mb_ltrim((string) $request->input('path', 'uploads'), '/'), '/')
            .'/'.str_replace(".$ext", '', $fileName);
        $path = $directory.'/'.$fileName;

        $file = new File([
            'name'             => $originalName,
            'path'             => $path,
            'type'             => $fileType,
            'size'             => (int) ($sizeBytes / 1000),
            'disk'             => config('filesystems.default'),
            'folder_id'        => $request->input('folder_id'),
            'responsive_paths' => ['original' => $path],
            'processed'        => false,
        ]);
        $file->save();

        $client  = $disk->getClient();
        $request = $client->createPresignedRequest(
            // ContentLength is part of the signed request, so S3 refuses a PUT
            // whose Content-Length differs from the size that was validated
            // above. Without it the server-side check is only as good as the
            // client's honesty: nothing stopped a caller declaring 1 KB, taking
            // the signed URL and writing a gigabyte.
            $client->getCommand('PutObject', [
                'Bucket'        => config('filesystems.disks.s3.bucket'),
                'Key'           => $path,
                'ContentType'   => $fileType,
                'ContentLength' => $sizeBytes,
            ]),
            now()->addMinutes(5)
        );

        return response()->json([
            'url'    => (string) $request->getUri(),
            'fileId' => $file->id,
        ]);
    }

    /**
     * Run post-upload processing for an object that has landed in the bucket:
     * record its real size and generate image variants, then mark it processed.
     *
     * In production this is what an S3 event / queue worker calls. In local dev
     * the upload client calls it directly, since there is no bucket event.
     * Idempotent — safe to call again if processing is retried.
     */
    public function process(Request $request, File $file, FileAccess $access, FileScanner $scanner, StorageQuota $quota): JsonResponse
    {
        // Same bare route-model bind the FileController methods had: without
        // this, any authenticated caller could trigger variant generation —
        // real image processing — against any file id in the system.
        abort_unless($access->canView($file, $request->user()), 404);

        $disk = Storage::disk($file->disk);
        $path = $file->responsive_paths['original'] ?? $file->path;

        if (! $disk->exists($path)) {
            throw new AppException('The uploaded object is not in storage yet.', 409);
        }

        $sizeKb = (int) ($disk->size($path) / 1000);

        // Later than the direct path can manage, and deliberately so: the
        // browser PUT straight to the bucket, so the object exists before the
        // app has ever seen a byte of it. Refusing here means deleting what
        // landed rather than preventing it — which is the honest limit of a
        // presigned design, and is why the README points a project that needs
        // the bytes never to land at bucket-level scanning instead.
        $uploaderId = $file->getAttribute($file->getCreatedByColumn());

        // Separate statuses because these are separate refusals: the scanner
        // rejects the CONTENT (422), the quota rejects the SIZE (413). Lumping
        // them meant an over-quota upload answered 413 on the direct path and
        // 422 here, for the same reason.
        if ($reason = $scanner->refuse($this->localCopyOf($disk, $path, $file->name))) {
            $file->delete();   // File::deleting unlinks the object too.

            throw new AppException($reason, 422);
        }

        if ($reason = $quota->refuse($uploaderId, $sizeKb)) {
            $file->delete();

            throw new AppException($reason, 413);
        }

        $file->forceFill(['size' => $sizeKb])->save();
        $file->processVariants();

        return response()->json(['file' => new FileResource($file->fresh())]);
    }

    /**
     * The object as an UploadedFile, so a scanner sees the same shape on both
     * storage paths and a project writes one implementation rather than two.
     */
    private function localCopyOf(Filesystem $disk, string $path, string $name): UploadedFile
    {
        $temp = tempnam(sys_get_temp_dir(), 'scan_');
        file_put_contents($temp, $disk->get($path));

        return new UploadedFile($temp, $name, null, null, true);
    }
}
