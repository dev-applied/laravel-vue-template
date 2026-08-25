<?php

declare(strict_types=1);

namespace Modules\Exports\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Exports\Http\Requests\StoreExportRequest;
use Modules\Exports\Http\Resources\ExportResource;
use Modules\Exports\Jobs\GenerateExport;
use Modules\Exports\Models\Export;
use Modules\Exports\Support\ExportRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Scoped to the authenticated user throughout — an export can contain anything
 * the source query returns, so one user must never reach another's file.
 */
class ExportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $exports = Export::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('id')
            ->vuetifyPaginate();

        $exports->setCollection(
            $exports->getCollection()->map(fn (Export $e) => new ExportResource($e))->collect()
        );

        return response()->json($exports);
    }

    /** The sources this project registered — drives the export menu. */
    public function sources(ExportRegistry $registry): JsonResponse
    {
        return response()->json([
            'sources' => collect($registry->all())
                ->map(fn ($source) => ['key' => $source->key, 'label' => $source->label])
                ->values(),
        ]);
    }

    public function store(StoreExportRequest $request, ExportRegistry $registry): JsonResponse
    {
        $source = $registry->get($request->string('source')->toString());

        // A source may name a policy ability; enforce it before queueing so a
        // user cannot export a listing they are not allowed to read.
        if ($source->ability !== null) {
            $this->authorize($source->ability, $source->resolveQuery([])->getModel());
        }

        $export = Export::create([
            'user_id' => $request->user()->getKey(),
            'source'  => $source->key,
            'format'  => $request->string('format', 'csv')->toString(),
            'status'  => Export::STATUS_PENDING,
            'filters' => (array) $request->input('filters', []),
        ]);

        GenerateExport::dispatch($export);

        return response()->json(['export' => new ExportResource($export->fresh())], 201);
    }

    /** Status poll target while the job runs. */
    public function show(Request $request, Export $export): JsonResponse
    {
        $this->ensureOwned($request, $export);

        return response()->json(['export' => new ExportResource($export)]);
    }

    /** @throws AppException */
    public function download(Request $request, Export $export): StreamedResponse
    {
        $this->ensureOwned($request, $export);

        if (! $export->isDownloadable()) {
            throw new AppException('That export is not ready to download.', 409);
        }

        return Storage::disk($export->disk)->download($export->path, $export->fileName());
    }

    public function destroy(Request $request, Export $export): JsonResponse
    {
        $this->ensureOwned($request, $export);
        $export->delete();

        return response()->json()->setStatusCode(204);
    }

    /**
     * 404 rather than 403 — a user has no business learning that another
     * user's export id exists.
     */
    private function ensureOwned(Request $request, Export $export): void
    {
        abort_unless($export->user_id === $request->user()->getKey(), 404);
    }
}
