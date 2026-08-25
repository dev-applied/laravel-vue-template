<?php

declare(strict_types=1);

namespace Modules\SavedViews\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * A seam for narrowing which saved views a request can see or write.
 *
 * The default does nothing. It exists because tenancy is the retrofit that
 * hurts: a multi-tenant project that adds scoping later has to find every
 * query in every per-user module and remember this one. Binding a scope here
 * covers the whole module in one place instead:
 *
 *   $this->app->bind(SavedViewScope::class, TenantScope::class);
 *
 * Shared views are the reason this matters. A per-user row is naturally
 * isolated; a row marked `is_shared` is visible to everyone on the same screen
 * key, and "everyone" must mean "everyone in this tenant".
 */
interface SavedViewScope
{
    /**
     * @param  Builder<\Modules\SavedViews\Models\SavedView>  $query
     */
    public function apply(Builder $query, mixed $user): void;

    /**
     * Extra attributes stamped onto a view as it is created — a tenant id,
     * typically.
     *
     * @return array<string, mixed>
     */
    public function attributes(mixed $user): array;

    /**
     * May this user work with saved views for this SCREEN?
     *
     * `key` arrives from the request as a free string and the module has no
     * idea which screens a project has, so without this there is nothing
     * stopping a low-privilege user from guessing `admin.users.index`,
     * `payroll.index`, `invoices.index` and reading back every SHARED view on
     * those screens — payload and owner name included. A payload is filters,
     * sort and column choices, which in practice embed record ids, search
     * terms and internal status codes.
     *
     * The shipped {@see NullScope} allows everything, because a module that
     * refused unknown keys would make saved views vanish on install for
     * reasons nobody could trace. Implement it the moment any screen behind
     * this is privileged:
     *
     *     public function allows(string $key, mixed $user): bool
     *     {
     *         return match (true) {
     *             str_starts_with($key, 'admin.') => $user?->can('admin.access') ?? false,
     *             default                         => true,
     *         };
     *     }
     */
    public function allows(string $key, mixed $user): bool;
}
