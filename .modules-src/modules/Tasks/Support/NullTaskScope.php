<?php

declare(strict_types=1);

namespace Modules\Tasks\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The shipped default: every signed-in user sees the whole board.
 *
 * Permissive because a shared board is the common shape and a module that
 * hid tasks on install would look broken. It is only safe as a default because
 * the destructive operations do NOT rely on it — see TaskScope.
 */
class NullTaskScope implements TaskScope
{
    public function apply(Builder $query, mixed $user): void
    {
        //
    }

    public function attributes(mixed $user): array
    {
        return [];
    }
}
