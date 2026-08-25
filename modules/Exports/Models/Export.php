<?php

declare(strict_types=1);

namespace Modules\Exports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Exports\Database\Factories\ExportFactory;
use Throwable;

class Export extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'source', 'format', 'status', 'filters',
        'disk', 'path', 'row_count', 'error', 'completed_at',
    ];

    protected $casts = [
        'filters'      => 'array',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the file is actually there, without letting the storage driver
     * decide the fate of the whole response.
     *
     * ExportResource calls this per row, so an export LISTING makes one remote
     * HEAD per record — and any of them can throw. Seen for real: S3
     * credentials that permit PutObject but not the HEAD that `exists()` needs,
     * so the export completed, the object was written, and then the listing
     * answered with a raw Flysystem line —
     *
     *     Unable to check existence for: exports/1-items-....csv
     *
     * — straight to the user, from outside any try in the module. The job's
     * own failures are redacted through safeReason(); this path had nothing.
     *
     * A storage failure means "cannot offer a download", not "the request
     * failed". The reason is logged, because a bucket that has stopped
     * answering is worth knowing about even when the UI degrades quietly.
     */
    public function isDownloadable(): bool
    {
        if ($this->status !== self::STATUS_COMPLETED || $this->path === null) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->exists($this->path);
        } catch (Throwable $e) {
            Log::warning('exports: could not check the stored file', [
                'export_id' => $this->getKey(),
                'disk'      => $this->disk,
                'exception' => $e::class,
                'message'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function fileName(): string
    {
        return $this->source.'-'.$this->created_at->format('Y-m-d-His').'.'.$this->format;
    }

    protected static function newFactory(): Factory
    {
        return ExportFactory::new();
    }

    protected static function booted(): void
    {
        parent::booted();

        // Never leave the generated file behind when the row goes.
        self::deleting(function (self $export) {
            if ($export->path && $export->disk) {
                Storage::disk($export->disk)->delete($export->path);
            }
        });
    }
}
