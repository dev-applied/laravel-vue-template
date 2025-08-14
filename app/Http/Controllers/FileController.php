<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AppException;
use App\Models\File;
use Auth;
use Bref\Event\InvalidLambdaEvent;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FileController extends Controller
{
    public function url(File $file, string $size = 'original'): BinaryFileResponse|RedirectResponse
    {
        if (! isset($file->responsive_paths[$size])) {
            $size = 'original';
        }

        $disk = Storage::disk($file->disk);

        if ($disk->providesTemporaryUrls()) {
            return response()->redirectTo($file->responsive_paths[$size]);
        } else {
            return response()->download($disk->path($file->responsive_paths[$size]), $file->name);
        }
    }

    public function download(File $file): BinaryFileResponse
    {
        return response()->download(Storage::disk($file->disk)->path($file->path));
    }

    /**
     * @throws AppException
     */
    public function show(File $file): JsonResponse
    {
        if ($file->processing_issues) {
            $file->delete();

            throw new AppException('File processing failed. Please try again.', 400);
        }

        // only uploader or someone on account can view file
        if ($file->created_by->account_id !== Auth::user()->account_id && Auth::id() !== $file->created_by_id) {
            throw new AppException('Unauthorized', 400);
        }

        return response()->json(compact('file'));
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validate(request(), [
            'file'     => 'required|file|max:20480',
            'id'       => 'nullable|integer',
            'kind'     => 'nullable|string',
            'duration' => 'nullable|integer',
        ], [
            'file.max' => 'This file is too large. Please upload a file less than 20MB.',
        ]);

        $media = DB::transaction(function () use ($data, $request) {
            if ($data['id'] ?? null) {
                $oldFile            = File::findOrFail($data['id']);
                $data['event_id']   = $oldFile->event_id;
                $data['event_data'] = $oldFile->event_data;
                $oldFile->delete();
            }

            $media = File::upload($request->file('file'), additionalData: $data);

            $media->load(['created_by', 'updated_by']);

            return $media;
        });

        return response()->json(['file' => $media]);
    }

    /**
     * @throws AppException
     */
    public function destroy(File $file): JsonResponse
    {
        // only uploader can view file
        if (Auth::id() !== $file->created_by_id) {
            throw new AppException('Unauthorized', 400);
        }

        $file->delete();

        return response()->json()->setStatusCode(204);
    }

    /**
     * @throws AppException
     */
    public function generatePresignedUrl(Request $request): JsonResponse
    {
        if (! Storage::disk()->providesTemporaryUrls()) {
            throw new AppException('Temporary URLs are not supported by the current storage disk.', 400);
        }

        $request->validate([
            'file_name' => 'required|string',
            'file_type' => 'required|string',
            'path'      => 'sometimes|nullable|string',
            'kind'      => 'sometimes|nullable|string',
            'duration'  => 'sometimes|nullable|integer',
            'id'        => 'sometimes|nullable|integer',
        ]);

        $fileName     = $request->input('file_name');
        $fileType     = $request->input('file_type');
        $uniqueNumber = time();
        $ext          = mb_strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName     = mb_strtolower(str_replace(".$ext", '', $fileName)."_$uniqueNumber.$ext");
        $directory    = mb_rtrim(mb_ltrim($request->input('path', 'uploads'), '/'), '/').'/'.str_replace(".$ext", '', $fileName);
        $path         = $directory.'/'.$fileName;

        if ($request->input('id')) {
            $oldFile = File::findOrFail($request->input('id'));
            $oldFile->delete();
        }

        $file                   = new File;
        $file->id               = $request->input('id');
        $file->name             = $fileName;
        $file->path             = $path;
        $file->type             = $fileType;
        $file->disk             = config('filesystems.default');
        $file->responsive_paths = [
            'original' => $path,
        ];
        $file->kind     = $request->input('kind');
        $file->duration = $request->input('duration');
        $file->size     = 0;
        $file->save();

        $url = Storage::disk()->getClient()->createPresignedRequest(
            Storage::disk()->getClient()->getCommand('PutObject', [
                'Bucket'      => env('AWS_BUCKET'),
                'Key'         => $path,
                'ContentType' => $fileType,
            ]),
            now()->addMinutes(5)
        );

        return response()->json([
            'url'    => (string) $url->getUri(),
            'fileId' => $file->id,
        ]);
    }

    /**
     * @throws AppException
     * @throws InvalidLambdaEvent
     */
    public function mockS3Event(File $file): JsonResponse
    {
        $file->mockS3Event();

        return response()->json([
            'message' => 'S3 event triggered successfully',
        ]);
    }
}
