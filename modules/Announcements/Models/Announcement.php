<?php

declare(strict_types=1);

namespace Modules\Announcements\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Announcements\Database\Factories\AnnouncementFactory;

class Announcement extends Model
{
    use HasFactory;

    public const LEVEL_INFO = 'info';

    public const LEVEL_SUCCESS = 'success';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_ERROR = 'error';

    public const LEVELS = [self::LEVEL_INFO, self::LEVEL_SUCCESS, self::LEVEL_WARNING, self::LEVEL_ERROR];

    public const PLACEMENT_BANNER = 'banner';

    public const PLACEMENT_MODAL = 'modal';

    public const PLACEMENTS = [self::PLACEMENT_BANNER, self::PLACEMENT_MODAL];

    public const AUDIENCE_EVERYONE = 'everyone';

    protected $fillable = [
        'title', 'body', 'level', 'placement', 'audience', 'dismissible',
        'requires_acknowledgement', 'action_label', 'action_url',
        'starts_at', 'ends_at', 'published_at',
    ];

    protected $casts = [
        'dismissible'              => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'starts_at'                => 'datetime',
        'ends_at'                  => 'datetime',
        'published_at'             => 'datetime',
    ];

    /** @return HasMany<AnnouncementDismissal, self> */
    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }

    /**
     * Published and inside its window right now.
     *
     * A null `starts_at` means "as soon as it is published" and a null
     * `ends_at` means "until someone unpublishes it" — both are the common
     * case, so neither should require filling in a date.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->whereNotNull('published_at')
            ->where('published_at', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    /**
     * Live announcements this user has not already dealt with.
     *
     * An announcement that requires acknowledgement is only cleared by an
     * acknowledgement — dismissing it is not enough, which is the whole point
     * of the flag.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnresolvedFor(Builder $query, ?User $user): void
    {
        if ($user === null) {
            return;
        }

        $query->whereDoesntHave('dismissals', function (Builder $q) use ($user) {
            $q->where('user_id', $user->getKey());

            // Column-aware: a required announcement needs acknowledged_at set
            // before the dismissal row counts as resolving it.
            $q->where(function (Builder $inner) {
                $inner->whereHas('announcement', fn (Builder $a) => $a->where('requires_acknowledgement', false))
                    ->orWhereNotNull('acknowledged_at');
            });
        });
    }

    public function isLive(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo($at)
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at))
            && ($this->ends_at === null || $this->ends_at->greaterThan($at));
    }

    protected static function newFactory(): Factory
    {
        return AnnouncementFactory::new();
    }
}
