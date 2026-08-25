<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Tests\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\RolesPermissions\Traits\HasAccessControl;

/**
 * The module cannot edit App\Models\User, so the suite exercises the trait on a
 * subclass backed by the same table — which is also exactly how a project
 * installs it.
 */
class AccessControlledUser extends User
{
    use HasAccessControl;

    protected $table = 'users';

    /**
     * Without this, ::factory() resolves Database\Factories\Modules\...\
     * from the subclass name and blows up. The companion factory reuses the
     * app's UserFactory definition but builds THIS class, so the trait is on
     * the instance the tests exercise.
     */
    protected static function newFactory(): Factory
    {
        return AccessControlledUserFactory::new();
    }
}
