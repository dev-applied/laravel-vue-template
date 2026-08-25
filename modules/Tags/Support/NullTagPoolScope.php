<?php

declare(strict_types=1);

namespace Modules\Tags\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The shipped default: every type is browsable, nothing is narrowed.
 *
 * Permissive on purpose — see {@see TagPoolScope::allows()} for why a refusing
 * default would be worse. Bind your own the moment a tag type is privileged.
 */
class NullTagPoolScope implements TagPoolScope
{
    public function allows(?string $type, mixed $user): bool
    {
        return true;
    }

    public function apply(Builder $query, mixed $user): void
    {
        //
    }
}
