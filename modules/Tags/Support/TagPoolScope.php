<?php

declare(strict_types=1);

namespace Modules\Tags\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * A seam for narrowing which tags the POOL endpoint hands back.
 *
 * Tags on one record are already gated: TaggableController runs the project's
 * registered ability before it will read or write them. `GET /tags` is the
 * other half and had no gate at all — any authenticated user could ask for the
 * pool of any `type` and read back every tag name in it.
 *
 * That matters because tag names are rarely neutral. In practice they carry
 * exactly the internal judgement a project does not publish: `at-risk`,
 * `legal-hold`, `do-not-contact`, `vip`, `churn-risk`, and often a client's
 * name. Reading the pool does not reveal WHICH records carry a tag, but it does
 * reveal that the category exists and roughly how heavily it is used — the
 * endpoint returns `usage_count`.
 *
 * The module cannot decide this for a project. A Tag's `type` is a free-form
 * grouping column, not a registry alias, so there is nothing to look up and no
 * ability to re-run:
 *
 *   $this->app->bind(TagPoolScope::class, StaffOnlyTagPool::class);
 */
interface TagPoolScope
{
    /**
     * May this user browse the pool for this type? `null` is the global pool.
     *
     * The shipped {@see NullTagPoolScope} allows everything, matching
     * SavedViews' NullScope and for the same reason: a module that refused
     * unknown types would make tag autocomplete vanish on install for reasons
     * nobody could trace, and most projects tag nothing sensitive. Implement it
     * the moment any type behind this is privileged:
     *
     *     public function allows(?string $type, mixed $user): bool
     *     {
     *         return match ($type) {
     *             'legal', 'risk' => $user?->can('staff.access') ?? false,
     *             default         => true,
     *         };
     *     }
     */
    public function allows(?string $type, mixed $user): bool;

    /**
     * Narrow the pool query itself — a tenant id, typically.
     *
     * @param  Builder<\Modules\Tags\Models\Tag>  $query
     */
    public function apply(Builder $query, mixed $user): void;
}
