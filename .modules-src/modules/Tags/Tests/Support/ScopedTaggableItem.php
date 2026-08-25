<?php

declare(strict_types=1);

namespace Modules\Tags\Tests\Support;

/**
 * Same table, but its tags are scoped to a type — so an "urgent" here is a
 * different row from a global "urgent".
 */
class ScopedTaggableItem extends TaggableItem
{
    public function tagType(): ?string
    {
        return 'item';
    }
}
