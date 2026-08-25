<?php

declare(strict_types=1);

namespace Modules\GlobalSearch\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $term
 * @property int $result_count
 */
class SearchHistory extends Model
{
    protected $table = 'search_histories';

    protected $fillable = ['user_id', 'term', 'result_count'];

    /**
     * Record a search, collapsing a repeat rather than appending it.
     *
     * Without the collapse the list is useless within a minute: a palette
     * searches on every keystroke, so typing "invoice" would file i, in, inv,
     * invo… and the recent list would show one word spelled out.
     */
    public static function remember(int $userId, string $term, int $resultCount): self
    {
        $entry = static::query()
            ->where('user_id', $userId)
            ->where('term', $term)
            ->first();

        if ($entry) {
            $entry->forceFill(['result_count' => $resultCount])->save();
            $entry->touch();

            return $entry;
        }

        return static::query()->create([
            'user_id'      => $userId,
            'term'         => $term,
            'result_count' => $resultCount,
        ]);
    }

    /** @param  Builder<SearchHistory>  $query */
    public function scopeRecentFor(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId)->latest('updated_at');
    }

    protected function casts(): array
    {
        return ['result_count' => 'integer'];
    }
}
