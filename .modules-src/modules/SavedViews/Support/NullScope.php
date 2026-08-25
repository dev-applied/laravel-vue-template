<?php

declare(strict_types=1);

namespace Modules\SavedViews\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The default: single-tenant, so nothing to narrow.
 */
class NullScope implements SavedViewScope
{
    public function apply(Builder $query, mixed $user): void
    {
        // Intentionally empty.
    }

    public function attributes(mixed $user): array
    {
        return [];
    }

    /**
     * Everything, deliberately.
     *
     * A module-level default that refused unknown screen keys would make saved
     * views disappear on install with no traceable cause. The refusal belongs
     * to the project, which is the only party that knows which of its screens
     * are privileged — see the contract's docblock.
     */
    public function allows(string $key, mixed $user): bool
    {
        return true;
    }
}
