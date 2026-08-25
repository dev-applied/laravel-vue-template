<?php

declare(strict_types=1);

namespace Modules\Favorites\Tests\Support;

use App\Models\Item;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Favorites\Concerns\Favoritable;

/**
 * A favouritable stand-in over the kernel's `items` table.
 *
 * The module cannot add the trait to a project's models from inside its own
 * tests, and it must not require the template to trait Item — so the tests
 * bring their own model pointed at a table that already exists.
 */
class FavoritableItem extends Item
{
    use Favoritable;

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
