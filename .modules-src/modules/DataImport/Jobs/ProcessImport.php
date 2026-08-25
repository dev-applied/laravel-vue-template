<?php

declare(strict_types=1);

namespace Modules\DataImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\DataImport\Models\DataImport;
use Modules\DataImport\Support\CsvReader;
use Modules\DataImport\Support\ImportRegistry;
use Modules\DataImport\Support\ImportTarget;
use Throwable;

/**
 * Applies a mapped file to its target.
 *
 * Each row is validated and persisted in its OWN transaction. A 5,000-row file
 * with one bad row must import 4,999 and tell you which one failed — wrapping
 * the batch would throw all of it away, which is the behaviour that makes
 * people import by hand instead.
 */
class ProcessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * Retries are safe ONLY because of the resume checkpoint below.
     *
     * Without it a retry re-reads the file from line 1, and since every row
     * commits on its own, a worker killed at row 18,000 re-imports 18,000
     * rows. That is not a hypothetical: a deploy, an OOM kill or a timeout all
     * produce it, and nothing in the result would say it happened.
     */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly DataImport $import,
        public readonly bool $dryRun = false,
    ) {}

    /**
     * Belt and braces against a double dispatch.
     *
     * The controller claims the import before dispatching, so two clicks
     * cannot both reach the queue — but a claim and a dispatch are two
     * statements, and anything that enqueues this job directly bypasses the
     * controller entirely. Overlap is dropped rather than released: releasing
     * would just requeue a duplicate forever.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('data-import:'.$this->import->getKey()))
                ->dontRelease()
                ->expireAfter($this->timeout),
        ];
    }

    public function handle(ImportRegistry $registry): void
    {
        if (! $this->dryRun) {
            $this->import->update(['status' => DataImport::STATUS_PROCESSING]);
        }

        $target = $registry->get($this->import->target);
        $result = $this->run($target);

        if ($this->dryRun) {
            return;
        }

        $this->import->update([
            'status'        => DataImport::STATUS_COMPLETED,
            'total_rows'    => $result['total'],
            'imported_rows' => $result['imported'],
            'failed_rows'   => $result['failed'],
            'errors'        => $result['errors'],
            'completed_at'  => now(),
        ]);
    }

    /**
     * Marking the import failed belongs here, not in a catch.
     *
     * A catch runs on EVERY attempt, so the first transient blip flipped the
     * status to failed while two retries were still queued — the UI said the
     * import had failed and then rows kept appearing. Laravel calls this once,
     * after the final attempt, which is exactly when the statement is true.
     */
    public function failed(Throwable $e): void
    {
        if ($this->dryRun) {
            return;
        }

        $this->import->update([
            'status'         => DataImport::STATUS_FAILED,
            'failure_reason' => $e->getMessage(),
        ]);
    }

    /**
     * @return array{total: int, imported: int, failed: int, errors: array<int, array<string, mixed>>}
     */
    public function run(ImportTarget $target): array
    {
        $mapping = (array) $this->import->mapping;
        $reader  = new CsvReader($this->import->localPath());

        // A dry run never resumes and never checkpoints: it writes nothing, so
        // there is no progress to protect, and skipping rows would report a
        // preview of the tail of the file as though it were the whole thing.
        $resumeAfter = $this->dryRun ? 0 : (int) $this->import->processed_rows;

        $headers  = [];
        $total    = $resumeAfter > 0 ? (int) $this->import->total_rows : 0;
        $imported = $resumeAfter > 0 ? (int) $this->import->imported_rows : 0;
        $failed   = $resumeAfter > 0 ? (int) $this->import->failed_rows : 0;
        $errors   = $resumeAfter > 0 ? (array) $this->import->errors : [];
        $line     = 1;
        $every    = max(1, DataImport::CHECKPOINT_EVERY);

        foreach ($reader->rows() as $row) {
            if ($headers === []) {
                $headers = $row;

                continue;
            }

            $line++;

            // Already done on an earlier attempt. The counters above were
            // seeded from what that attempt recorded, so the totals stay whole
            // across a resume instead of reporting only the tail.
            if ($line <= $resumeAfter) {
                continue;
            }

            $total++;

            $mapped = $this->mapRow($headers, $row, $mapping);
            $check  = Validator::make($mapped, $target->rules);

            if ($check->fails()) {
                $failed++;

                if (count($errors) < DataImport::MAX_RETAINED_ERRORS) {
                    $errors[] = ['line' => $line, 'errors' => $check->errors()->all()];
                }

                $this->checkpoint($line, $total, $imported, $failed, $errors, $every);

                continue;
            }

            if ($this->dryRun) {
                $imported++;

                continue;
            }

            try {
                DB::transaction(fn () => $target->handle($check->validated(), $line));
                $imported++;
            } catch (Throwable $e) {
                $failed++;

                if (count($errors) < DataImport::MAX_RETAINED_ERRORS) {
                    $errors[] = ['line' => $line, 'errors' => [$e->getMessage()]];
                }
            }

            $this->checkpoint($line, $total, $imported, $failed, $errors, $every);
        }

        if (! $this->dryRun) {
            $this->writeCheckpoint($line, $total, $imported, $failed, $errors);
        }

        return ['total' => $total, 'imported' => $imported, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Persist progress every N rows — see DataImport::CHECKPOINT_EVERY.
     *
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function checkpoint(int $line, int $total, int $imported, int $failed, array $errors, int $every): void
    {
        if ($this->dryRun || $total % $every !== 0) {
            return;
        }

        $this->writeCheckpoint($line, $total, $imported, $failed, $errors);
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function writeCheckpoint(int $line, int $total, int $imported, int $failed, array $errors): void
    {
        $this->import->forceFill([
            'processed_rows' => $line,
            'total_rows'     => $total,
            'imported_rows'  => $imported,
            'failed_rows'    => $failed,
            'errors'         => $errors,
        ])->save();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $row
     * @param  array<string, string>  $mapping  target field => source header
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $row, array $mapping): array
    {
        $byHeader = [];

        foreach ($headers as $index => $header) {
            $byHeader[$header] = $row[$index] ?? null;
        }

        $mapped = [];

        foreach ($mapping as $field => $sourceHeader) {
            $value = $byHeader[$sourceHeader] ?? null;
            // '' and 'null' both mean "absent" in a spreadsheet; passing '' to a
            // nullable|integer rule fails in a way that confuses everyone.
            $mapped[$field] = ($value === '' ? null : $value);
        }

        return $mapped;
    }
}
