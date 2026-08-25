<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Tests\Support;

use Database\Factories\UserFactory;

/**
 * UserFactory builds App\Models\User, so ::factory() on the subclass would
 * hand back a plain User with no HasAccessControl. Reuse the app's definition
 * but build the subclass.
 *
 * @extends UserFactory<AccessControlledUser>
 */
class AccessControlledUserFactory extends UserFactory
{
    protected $model = AccessControlledUser::class;
}
