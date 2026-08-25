<?php

declare(strict_types=1);

namespace Modules\Tags\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Modules\Tags\Models\Tag;

/**
 * Add to any model that should accept tags.
 *
 *   class Order extends Model
 *   {
 *       use HasTags;
 *
 *       // Optional: scope this model's tags so an "urgent" here is a
 *       // different tag from an "urgent" on tickets.
 *       public function tagType(): ?string { return 'order'; }
 *   }
 */
trait HasTags
{
    /** @return MorphToMany<Tag, self> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    /**
     * Null means this model's tags come from the global pool.
     */
    public function tagType(): ?string
    {
        return null;
    }

    /**
     * Replace this record's tags with exactly these names.
     *
     * @param  list<string>  $names
     */
    public function syncTags(array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            if (mb_trim((string) $name) === '') {
                continue;
            }

            $ids[] = Tag::findOrCreateNamed((string) $name, $this->tagType())->getKey();
        }

        $changes = $this->tags()->sync(array_unique($ids));

        $this->recountTags([...$changes['attached'], ...$changes['detached']]);
    }

    /**
     * @param  list<string>  $names
     */
    public function attachTags(array $names): void
    {
        $ids = [];

        foreach ($names as $name) {
            if (mb_trim((string) $name) === '') {
                continue;
            }

            $ids[] = Tag::findOrCreateNamed((string) $name, $this->tagType())->getKey();
        }

        // syncWithoutDetaching, not attach: attaching a tag the record already
        // has violates the unique index and 500s an otherwise harmless action.
        $changes = $this->tags()->syncWithoutDetaching(array_unique($ids));

        $this->recountTags($changes['attached']);
    }

    public function detachTag(Tag|int $tag): void
    {
        $id = $tag instanceof Tag ? $tag->getKey() : $tag;

        $this->tags()->detach($id);

        $this->recountTags([$id]);
    }

    /**
     * Records carrying every one of these tags.
     *
     * AND, not OR: "urgent" plus "billing" should narrow to the overlap.
     * Filtering usually means narrowing, and an OR filter that widens the list
     * as you add terms is the surprising one.
     *
     * @param  Builder<self>  $query
     * @param  list<string>  $slugs
     */
    public function scopeWithAllTags(Builder $query, array $slugs): void
    {
        foreach (array_filter($slugs) as $slug) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $slug));
        }
    }

    /**
     * Records carrying any of these tags.
     *
     * @param  Builder<self>  $query
     * @param  list<string>  $slugs
     */
    public function scopeWithAnyTags(Builder $query, array $slugs): void
    {
        $slugs = array_values(array_filter($slugs));

        if ($slugs === []) {
            return;
        }

        $query->whereHas('tags', fn (Builder $q) => $q->whereIn('slug', $slugs));
    }

    /**
     * Keep usage_count honest.
     *
     * Counted rather than incremented: an increment drifts the first time a
     * record is deleted with tags still on it, and a wrong count is worse than
     * no count because the tag picker sorts on it.
     *
     * @param  list<int|string>  $tagIds
     */
    protected function recountTags(array $tagIds): void
    {
        foreach (array_unique($tagIds) as $tagId) {
            $tag = Tag::find($tagId);

            $tag?->update([
                'usage_count' => DB::table('taggables')->where('tag_id', $tagId)->count(),
            ]);
        }
    }
}
