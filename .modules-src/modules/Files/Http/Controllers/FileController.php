<?php

declare(strict_types=1);

namespace Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Files\Http\Requests\StoreFileRequest;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Models\File;
use Modules\Files\Support\FileAccess;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private readonly FileAccess $access) {}

    /**
     * JSON metadata. This is what an upload client polls while waiting for the
     * presigned-S3 path to finish generating variants — `url`/`view`/`download`
     * all return binary or a redirect and can never be polled.
     */
    public function show(Request $request, File $file): JsonResponse
    {
        $this->assertCanView($request, $file);

        return response()->json(['file' => new FileResource($file)]);
    }

    /** Redirect to a temporary URL when the disk supports one, else stream the bytes. */
    public function url(Request $request, File $file, string $size = 'original'): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        $this->assertCanView($request, $file);

        $disk = Storage::disk($file->disk);
        $path = $file->pathForSize($size);

        if ($disk->providesTemporaryUrls()) {
            return response()->redirectTo($disk->temporaryUrl($path, now()->addMinutes(5)));
        }

        return $disk->download($path, $file->name);
    }

    public function view(Request $request, File $file): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        return $this->url($request, $file);
    }

    public function download(Request $request, File $file, string $size = 'original'): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        return $this->url($request, $file, $size);
    }

    public function store(StoreFileRequest $request): JsonResponse
    {
        $file = DB::transaction(fn (): File => File::upload($request->file('file')));

        return response()->json(['file' => new FileResource($file)]);
    }

    public function destroy(Request $request, File $file): JsonResponse
    {
        // Deleting also unlinks the bytes (File::deleting), so this is the
        // destructive half and gets its own decision rather than reusing view.
        abort_unless($this->access->canDelete($file, $request->user()), 404);

        $file->delete();

        return response()->json()->setStatusCode(204);
    }

    /**
     * 404, not 403, on a refusal.
     *
     * File ids are sequential, so a 403 here answers "this id exists" for every
     * id a caller cares to try — the enumeration is the whole attack this
     * module was open to. A refusal and a missing row are indistinguishable.
     */
    protected function assertCanView(Request $request, File $file): void
    {
        abort_unless($this->access->canView($file, $request->user()), 404);
    }
}
