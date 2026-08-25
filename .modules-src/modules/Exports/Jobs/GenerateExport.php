<?php

declare(strict_types=1);

namespace Modules\Exports\Jobs;

use App\Exceptions\AppException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Exports\Models\Export;
use Modules\Exports\Support\CsvWriter;
use Modules\Exports\Support\ExportRegistry;
use Modules\Exports\Support\RowWriter;
use Throwable;

/**
 * Streams a registered source to a file and marks the Export row completed.
 *
 * chunkById rather than chunk: the query is ordered by id and paginated by the
 * last seen id, so rows inserted mid-export cannot shift a page boundary and
 * duplicate or skip records.
 */
class GenerateExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public readonly Export $export) {}

    public function handle(ExportRegistry $registry): void
    {
        $this->export->update(['status' => Export::STATUS_PROCESSING]);

        $temp = null;

        try {
            $source  = $registry->get($this->export->source);
            $writer  = $this->writerFor($this->export->format);
            $columns = $source->columns;

            $temp = tempnam(sys_get_temp_dir(), 'export').'.'.$this->export->format;
            $writer->open($temp, array_values($columns));

            $rows = 0;
            $source->resolveQuery($this->export->filters ?? [])
                ->chunkById(500, function ($chunk) use ($writer, $columns, $source, &$rows): void {
                    foreach ($chunk as $record) {
                        $writer->write(array_map(
                            fn (string $column): mixed => $source->cell(Arr::get($record, $column), $column),
                            array_keys($columns)
                        ));
                        $rows++;
                    }
                });

            $writer->close();

            $disk = config('filesystems.default');
            $path = 'exports/'.$this->export->id.'-'.$this->export->fileName();
            Storage::disk($disk)->put($path, (string) file_get_contents($temp));

            $this->export->update([
                'status'       => Export::STATUS_COMPLETED,
                'disk'         => $disk,
                'path'         => $path,
                'row_count'    => $rows,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Surface the reason on the row — the user is staring at a status
            // poll and "failed" with no explanation is useless to them — but
            // only a reason they are allowed to read. See safeReason().
            $this->export->update([
                'status' => Export::STATUS_FAILED,
                'error'  => $this->safeReason($e, 'exports: generation failed'),
            ]);

            throw $e;
        } finally {
            if ($temp !== null && file_exists($temp)) {
                unlink($temp);
            }
        }
    }

    private function writerFor(string $format): RowWriter
    {
        // XlsxWriter only exists when the module was installed with
        // formats=csv+xlsx, so resolve it by name rather than importing it.
        if ($format === 'xlsx' && class_exists('Modules\\Exports\\Support\\XlsxWriter')) {
            /** @var RowWriter $writer */
            $writer = new ('Modules\\Exports\\Support\\XlsxWriter');

            return $writer;
        }

        return new CsvWriter;
    }

    /**
     * A message the person who started this is allowed to read.
     *
     * The reason has to reach them — a status of "failed" with no explanation
     * is useless and they will just retry it — but `$e->getMessage()` on an
     * arbitrary Throwable is whatever the driver said, and what a driver says
     * is "SQLSTATE[42S02]: Base table or view not found: 1146 Table
     * 'acme_prod.legacy_users' doesn't exist", or a connection string, or a
     * file path on the server.
     *
     * So: exceptions the application THREW ON PURPOSE are written for a person
     * and pass through. Anything else becomes a generic line plus a reference,
     * and the real text goes to the log where support can match it up.
     */
    private function safeReason(Throwable $e, string $context): string
    {
        if ($e instanceof AppException || $e instanceof ValidationException) {
            return $e->getMessage();
        }

        $reference = (string) Str::uuid();

        Log::error($context, [
            'reference' => $reference,
            'exception' => $e::class,
            'message'   => $e->getMessage(),
        ]);

        report($e);

        return "Something went wrong on our side. (Reference: {$reference})";
    }
}
