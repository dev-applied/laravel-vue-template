<?php

declare(strict_types=1);

namespace Modules\Files\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Files\Http\Requests\GeneratePresignedUrlRequest;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Models\File;
use Modules\Files\Support\FileAccess;

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
    public function generate(GeneratePresignedUrlRequest $request): JsonResponse
    {
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
            'size'             => 0,
            'disk'             => config('filesystems.default'),
            'responsive_paths' => ['original' => $path],
            'processed'        => false,
        ]);
        $file->save();

        $client  = $disk->getClient();
        $request = $client->createPresignedRequest(
            $client->getCommand('PutObject', [
                'Bucket'      => config('filesystems.disks.s3.bucket'),
                'Key'         => $path,
                'ContentType' => $fileType,
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
    public function process(Request $request, File $file, FileAccess $access): JsonResponse
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

        $file->forceFill(['size' => (int) ($disk->size($path) / 1000)])->save();
        $file->processVariants();

        return response()->json(['file' => new FileResource($file->fresh())]);
    }
}
