<?php

declare(strict_types=1);

namespace Modules\Tags\Tests\Support;

use App\Models\Item;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tags\Traits\HasTags;

/**
 * A taggable stand-in over the kernel's `items` table.
 *
 * The module cannot add HasTags to a project's models from inside its own
 * tests, and it must not require the template to trait Item — so the tests
 * bring their own model pointed at a table that already exists.
 */
class TaggableItem extends Item
{
    use HasTags;

    protected $table = 'items';

    /**
     * Factory resolution walks the class name, so a subclass looks for a
     * factory that does not exist. Point it back at the kernel's.
     */
    protected static function newFactory(): Factory
    {
        return ItemFactory::new();
    }
}
