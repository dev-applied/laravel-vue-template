<?php

declare(strict_types=1);

namespace Modules\GlobalSearch\Support;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Builds a registry `query` closure from a Scout-searchable model.
 *
 * The registry takes a closure returning a builder, which is all a project
 * needs — including for Scout, since `Model::search($term)->query(...)` ends in
 * an Eloquent builder too. This exists so the eager-load and constraint half is
 * not retyped at every call site:
 *
 *   query: ScoutSearchSource::for(Item::class, with: ['owner']),
 *
 * Only worth installing when the project already runs Scout against a real
 * engine. On Scout's own `database` driver this is a slower LIKE than writing
 * the LIKE yourself, because it round-trips through Scout's builder first.
 */
class ScoutSearchSource
{
    /**
     * @param  class-string  $model
     * @param  array<int, string>  $with
     * @return Closure(string): Builder<*>
     */
    public static function for(string $model, array $with = [], ?Closure $constrain = null): Closure
    {
        return function (string $term) use ($model, $with, $constrain): Builder {
            /** @var Builder<*> $builder */
            $builder = $model::search($term)->query(function (Builder $query) use ($with, $constrain) {
                if ($with !== []) {
                    $query->with($with);
                }

                if ($constrain) {
                    $constrain($query);
                }
            })->getQuery();

            return $builder;
        };
    }
}
