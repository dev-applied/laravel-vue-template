<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Imagine\Imagick\Imagine;
use Imagine\Image\Box;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;
use Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    public function image($id, $size = 'original'): RedirectResponse
    {
        /** @var File $file */
        $file = File::findOrFail($id);
        if (!isset($file->responsive_images[$size])) {
            $size = 'original';

        }
        return response()->redirectTo($file->responsive_images[$size]);
    }

    /**
     * @throws ValidationException
     */
    public function upload(): JsonResponse {
        $data = $this->validate(request(), [
            'file' => 'required|file|max:20480',
        ], [
            'file.max' => 'This file is too large. Please upload a file less than 20MB.',
        ]);

        $file = request()->file('file');
        $uniqueNumber = time();
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $fileName = strtolower(str_replace(".$ext", '', $file->getClientOriginalName()) . "_$uniqueNumber.$ext");
        $directory = 'uploads/' . str_replace(".$ext", '', $fileName);
        $path = $file->storePubliclyAs($directory, $fileName, 'public');

        $media = File::create([
            'name' => $file->getClientOriginalName(),
            'url' => $path,
            'type' => $mime,
            'size' => $file->getSize() / 1000, // size in kilobytes
        ]);

        if (str_contains($mime, 'image')) {
            foreach (File::SIZES as $name => $dims) {
                $tmp = tempnam(sys_get_temp_dir(), $name) . '.' . $file->getClientOriginalExtension();
                $image = new Imagine;
                $image = $image->open($file->getRealPath());
                if ($file->getClientOriginalExtension() !== 'svg') {
                    $image = $image->thumbnail(new Box($dims['width'], $dims['height']));
                }
                $image->save($tmp);
                ImageOptimizer::optimize($tmp);
                $thumbnail = new UploadedFile($tmp, $file->getClientOriginalName());
                $thumbnail->storePubliclyAs($directory, str_replace(".$ext", '', $fileName) . "_$name.$ext", 'public');
                unlink($tmp);
            }
        }

        $media->load(['created_by', 'updated_by']);

        return response()->json(['file' => $media]);
    }

    public function view($id): JsonResponse
    {
        $file = File::findOrFail($id);

        return response()->json(compact('file'));
    }

    public function download($id): BinaryFileResponse
    {
        /** @var File $file */
        $file = File::findOrFail($id);
        return response()->download(Storage::disk('public')->path($file->getRawOriginal('url')));
    }

    public function destroy(File $file): JsonResponse
    {
        $file->delete();

        return response()->json()->setStatusCode(204);
    }
}
