<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStatus;
use App\Traits\WhoDidIt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory;
    use SoftDeletes;
    use WhoDidIt;

    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
        'due_date',
        'owner_id',
    ];

    protected $casts = [
        'status'   => ItemStatus::class,
        'priority' => 'integer',
        'due_date' => 'date',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Apply UI-side filters to the query.
     *
     * Accepted keys: status (string), owner_id (int), search (string).
     * Unknown keys are ignored — controllers can pass `request()->all()` safely.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['owner_id'] ?? null, fn ($q, $ownerId) => $q->where('owner_id', (int) $ownerId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                // Escape LIKE wildcards in user input so a search for "100%"
                // doesn't degenerate into a "contains 100" match.
                $escaped = addcslashes((string) $search, '%_\\');
                $q->where(function (Builder $sub) use ($escaped) {
                    $sub->where('name', 'like', "%{$escaped}%")
                        ->orWhere('description', 'like', "%{$escaped}%");
                });
            });
    }
}
