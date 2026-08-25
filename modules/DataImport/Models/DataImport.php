<?php

declare(strict_types=1);

namespace Modules\DataImport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\DataImport\Database\Factories\DataImportFactory;

class DataImport extends Model
{
    use HasFactory;

    /** How many per-row errors are retained. Beyond this the count still rises. */
    public const MAX_RETAINED_ERRORS = 100;

    /**
     * How often progress is written while an import runs.
     *
     * Per-row would be exact and would also double the write cost of every
     * import, so the residual is bounded rather than removed: a worker killed
     * mid-file replays at most this many rows on its retry. A target that
     * cannot tolerate that should upsert — `handle()` receives the line number
     * precisely so it can.
     */
    public const CHECKPOINT_EVERY = 100;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'target', 'original_name', 'disk', 'path', 'status', 'mapping',
        'total_rows', 'imported_rows', 'failed_rows', 'processed_rows', 'errors', 'failure_reason', 'completed_at',
    ];

    protected $casts = [
        'mapping'      => 'array',
        'errors'       => 'array',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function localPath(): string
    {
        // Readers need a real filesystem path; remote disks get pulled down.
        $disk = Storage::disk($this->disk);

        if (method_exists($disk, 'path') && file_exists($disk->path($this->path))) {
            return $disk->path($this->path);
        }

        $temp = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($temp, $disk->get($this->path));

        return $temp;
    }

    protected static function newFactory(): Factory
    {
        return DataImportFactory::new();
    }

    protected static function booted(): void
    {
        parent::booted();

        self::deleting(function (self $import) {
            if ($import->disk && $import->path) {
                Storage::disk($import->disk)->delete($import->path);
            }
        });
    }
}
