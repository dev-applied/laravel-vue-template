<?php

declare(strict_types=1);

namespace Modules\Favorites\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Favorites\Models\Favorite;

/**
 * Put on any model a project registers as favouritable.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait Favoritable
{
    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function isFavoritedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // Prefers an already-loaded relation so a list that eager-loaded
        // `favorites` does not fire one query per row — the N+1 this trait
        // would otherwise invite on exactly the screens that use it.
        if ($this->relationLoaded('favorites')) {
            return $this->favorites->contains(fn (Favorite $f) => $f->user_id === $user->getKey());
        }

        return $this->favorites()->where('user_id', $user->getKey())->exists();
    }

    /** Only the records this user has starred. */
    public function scopeFavoritedBy(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            // Fail closed: no user means no favourites, not everyone's.
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('favorites', fn (Builder $q) => $q->where('user_id', $user->getKey()));
    }

    /**
     * Eager-load just this user's favourite rows.
     *
     * `with('favorites')` would load EVERY user's, which is both a pointless
     * amount of data and a disclosure — the count of who else starred a record
     * is not something a list endpoint should leak.
     */
    public function scopeWithFavoritedBy(Builder $query, ?User $user): Builder
    {
        return $query->with(['favorites' => fn ($q) => $q->where('user_id', $user?->getKey() ?? 0)]);
    }
}
