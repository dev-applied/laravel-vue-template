<?php

declare(strict_types=1);

namespace Modules\Exports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\Exports\Database\Factories\ExportFactory;

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

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->path !== null
            && Storage::disk($this->disk)->exists($this->path);
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
