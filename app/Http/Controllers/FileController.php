<?php

namespace App\Http\Controllers;

use App\Enums\Roles;
use App\Exceptions\AppException;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * @param  File  $file
     * @param  string  $size
     * @return StreamedResponse
     */
    public function url(File $file, string $size = 'original'): StreamedResponse
    {
        if (!isset($file->responsive_paths[$size])) {
            $size = 'original';
        }

        return Storage::disk($file->disk)->response($file->responsive_paths[$size]);
    }



    /**
     * @param  Request  $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse {
        $this->validate($request, [
            'file' => 'required|file|max:20480',
        ], [
            'file.max' => 'This file is too large. Please upload a file less than 20MB.',
        ]);

        $media = File::upload($request->file('file'));

        $media->load(['created_by', 'updated_by']);

        return response()->json(['file' => $media]);
    }

    /**
     * @param  File  $file
     * @return BinaryFileResponse
     */
    public function download(File $file): BinaryFileResponse
    {
        return response()->download(Storage::disk($file->disk)->path($file->path));
    }

    /**
     * @param  File  $file
     * @return JsonResponse
     */
    public function destroy(File $file): JsonResponse
    {
        $file->delete();

        return response()->json()->setStatusCode(204);
    }}
