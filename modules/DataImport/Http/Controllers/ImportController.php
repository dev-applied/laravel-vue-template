<?php

declare(strict_types=1);

namespace Modules\DataImport\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\DataImport\Http\Requests\RunImportRequest;
use Modules\DataImport\Http\Requests\StoreImportRequest;
use Modules\DataImport\Http\Resources\ImportResource;
use Modules\DataImport\Jobs\ProcessImport;
use Modules\DataImport\Models\DataImport;
use Modules\DataImport\Support\CsvReader;
use Modules\DataImport\Support\ImportRegistry;

/**
 * Four steps, four endpoints: upload → inspect headers → dry-run → commit.
 * Everything is scoped to the uploading user; an import file routinely contains
 * data one user should not hand to another.
 */
class ImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $imports = DataImport::query()
            ->where('user_id', $request->user()->getKey())
            ->latest('id')
            ->vuetifyPaginate();

        $imports->setCollection(
            $imports->getCollection()->map(fn (DataImport $i) => new ImportResource($i))->collect()
        );

        return response()->json($imports);
    }

    /** The targets this project registered, with their fields — drives the wizard. */
    public function targets(ImportRegistry $registry): JsonResponse
    {
        return response()->json([
            'targets' => collect($registry->all())->map(fn ($target) => [
                'key'      => $target->key,
                'label'    => $target->label,
                'fields'   => $target->fields,
                'required' => $target->required,
            ])->values(),
        ]);
    }

    /** Step 1 — store the file and hand back its headers plus a sample. */
    public function store(StoreImportRequest $request, ImportRegistry $registry): JsonResponse
    {
        $target = $registry->get($request->string('target')->toString());

        if ($target->ability !== null) {
            $this->authorize($target->ability);
        }

        $file = $request->file('file');
        $path = $file->store('imports', config('filesystems.default'));

        // store() returns FALSE when the disk write fails — a misconfigured or
        // unreachable S3 bucket is the ordinary case — and false lands in a
        // string column as "0". Unchecked, the upload appeared to succeed: an
        // import row was created pointing at a file named "0", headers came
        // back empty so nothing could be mapped, and every later step failed
        // for reasons that had nothing to do with the real cause. Found by
        // driving the wizard in a browser against a disk with no credentials.
        if ($path === false) {
            throw new AppException(
                'The file could not be stored. Check the '.config('filesystems.default').' disk configuration.',
                500
            );
        }

        $import = DataImport::create([
            'user_id'       => $request->user()->getKey(),
            'target'        => $target->key,
            'original_name' => $file->getClientOriginalName(),
            'disk'          => config('filesystems.default'),
            'path'          => $path,
            'status'        => DataImport::STATUS_UPLOADED,
        ]);

        return response()->json([
            'import'    => new ImportResource($import),
            'headers'   => $this->headersFor($import),
            'sample'    => $this->sampleFor($import),
            'suggested' => $this->suggestMapping($import, $target->fields),
        ], 201);
    }

    /** Step 2/3 — validate the mapping against the file without writing anything. */
    public function dryRun(RunImportRequest $request, DataImport $import, ImportRegistry $registry): JsonResponse
    {
        $this->ensureOwned($request, $import);
        $target = $registry->get($import->target);

        $this->assertRequiredFieldsMapped($target->required, (array) $request->input('mapping', []));

        $import->update(['mapping' => (array) $request->input('mapping', [])]);

        $result = (new ProcessImport($import, dryRun: true))->run($target);

        return response()->json(['result' => $result]);
    }

    /** Step 4 — commit. */
    public function run(RunImportRequest $request, DataImport $import, ImportRegistry $registry): JsonResponse
    {
        $this->ensureOwned($request, $import);
        $target = $registry->get($import->target);

        $mapping = (array) $request->input('mapping', []);

        $this->assertRequiredFieldsMapped($target->required, $mapping);

        // Re-mapping an import that has already written rows would apply the
        // new interpretation to the tail of the file and leave the head under
        // the old one — a half-and-half result nobody can reason about, and
        // one the progress numbers would not reveal. The already-written rows
        // are committed and cannot be taken back, so the honest answer is to
        // refuse and let them upload the file again.
        // Compared order-insensitively, and that is not defensive coding.
        // MySQL 8 stores JSON in a normalised binary form that SORTS object
        // keys; MariaDB stores it as text and preserves them. So the same
        // mapping written as [first_name, email] reads back as [email,
        // first_name] on MySQL and matches on MariaDB — and `!==` on arrays
        // compares order. Left strict, this guard fired on every legitimate
        // resume in production while passing locally, which is exactly how it
        // was found: green on MariaDB here, red on MySQL 8 in CI.
        $submitted = $mapping;
        $stored    = (array) $import->mapping;
        ksort($submitted);
        ksort($stored);

        if ((int) $import->processed_rows > 0 && $submitted !== $stored) {
            throw new AppException(
                'This import has already written '.$import->imported_rows.' rows using the previous column mapping. Upload the file again to import it a different way.',
                409
            );
        }

        // Claim it. Two clicks on "Start import" used to dispatch two jobs over
        // the same file, and because each row commits on its own the result was
        // every row imported twice with nothing anywhere reporting it. Only an
        // import that is not already running can be started, and the database
        // decides which of the two racing requests that is.
        $claimed = DataImport::query()
            ->whereKey($import->getKey())
            ->whereIn('status', [DataImport::STATUS_UPLOADED, DataImport::STATUS_FAILED])
            ->update([
                'mapping'        => json_encode($mapping),
                'status'         => DataImport::STATUS_PROCESSING,
                'failure_reason' => null,
            ]);

        if ($claimed === 0) {
            throw new AppException('This import is already running or has already finished.', 409);
        }

        ProcessImport::dispatch($import->fresh());

        return response()->json(['import' => new ImportResource($import->fresh())], 202);
    }

    public function show(Request $request, DataImport $import): JsonResponse
    {
        $this->ensureOwned($request, $import);

        return response()->json(['import' => new ImportResource($import)]);
    }

    public function destroy(Request $request, DataImport $import): JsonResponse
    {
        $this->ensureOwned($request, $import);
        $import->delete();

        return response()->json()->setStatusCode(204);
    }

    /** @param array<int, string> $required @param array<string, string> $mapping @throws AppException */
    private function assertRequiredFieldsMapped(array $required, array $mapping): void
    {
        $missing = array_diff($required, array_keys(array_filter($mapping)));

        if ($missing !== []) {
            throw new AppException('These fields must be mapped before importing: '.implode(', ', $missing), 422);
        }
    }

    /** @return array<int, string> */
    private function headersFor(DataImport $import): array
    {
        return (new CsvReader($import->localPath()))->headers();
    }

    /** @return array<int, array<int, string>> */
    private function sampleFor(DataImport $import): array
    {
        $rows = iterator_to_array((new CsvReader($import->localPath()))->rows(6));

        return array_slice($rows, 1);   // drop the header row
    }

    /**
     * Pre-fill the mapping where a header obviously matches a field, so the
     * common case is "confirm" rather than "fill in twelve dropdowns".
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function suggestMapping(DataImport $import, array $fields): array
    {
        $normalise = fn (string $v): string => preg_replace('/[^a-z0-9]/', '', mb_strtolower($v)) ?? '';
        $headers   = $this->headersFor($import);

        $suggested = [];

        foreach ($fields as $field => $label) {
            foreach ($headers as $header) {
                if (in_array($normalise($header), [$normalise($field), $normalise($label)], true)) {
                    $suggested[$field] = $header;

                    break;
                }
            }
        }

        return $suggested;
    }

    private function ensureOwned(Request $request, DataImport $import): void
    {
        abort_unless($import->user_id === $request->user()->getKey(), 404);
    }
}
