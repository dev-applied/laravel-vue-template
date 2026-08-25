<?php

declare(strict_types=1);

namespace Modules\Users\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * OPTIONAL sugar for the User model.
 *
 *   class User extends Authenticatable
 *   {
 *       use ManagesUsers;
 *   }
 *
 * The module works without it — the controller queries the column directly —
 * so a fresh `module:add` is never broken waiting on a manual edit. Add it to
 * get `$user->isActive()` and `User::active()` at call sites.
 */
trait ManagesUsers
{
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDeactivated(Builder $query): void
    {
        $query->whereNotNull('deactivated_at');
    }
}
