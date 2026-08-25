<?php

declare(strict_types=1);

namespace Modules\Tags\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use Modules\Tags\Database\Factories\TagFactory;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color', 'type', 'usage_count'];

    protected $casts = ['usage_count' => 'integer'];

    /**
     * Find or create by normalised name.
     *
     * The slug is the identity — "Urgent", "urgent" and " URGENT " all resolve
     * to the same tag. Without that a tag list becomes three near-duplicates
     * and no filter on it is trustworthy.
     */
    public static function findOrCreateNamed(string $name, ?string $type = null): self
    {
        $name = mb_trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $slug = static::slugFor($name, $type);

        return static::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'type' => $type]
        );
    }

    public static function slugFor(string $name, ?string $type = null): string
    {
        $slug = Str::slug($name) ?: mb_strtolower(mb_trim($name));

        // The type is folded into the slug rather than kept as a separate
        // unique pair: a unique index over (slug, type) treats every NULL type
        // as distinct in MySQL, so global tags would duplicate freely.
        return $type === null ? $slug : $type.':'.$slug;
    }

    /** @return MorphToMany<Model, self> */
    public function taggables(string $class): MorphToMany
    {
        return $this->morphedByMany($class, 'taggable');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, ?string $type): void
    {
        // A null type asks for global tags specifically, not for "any type".
        $query->where('type', $type);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->where('name', 'like', '%'.$term.'%');
    }

    protected static function newFactory(): Factory
    {
        return TagFactory::new();
    }
}
