<?php

declare(strict_types=1);

namespace Modules\Announcements\Support;

use Modules\Announcements\Models\Announcement;

/**
 * Who sees an announcement.
 *
 * The module deliberately does not know. Targeting "all editors" needs a role
 * system; targeting "everyone on the Pro plan" needs billing. Binding either
 * assumption into this module would make it refuse to install anywhere the
 * assumption is false, so the project binds its own implementation instead:
 *
 *   $this->app->bind(AudienceResolver::class, MyAudienceResolver::class);
 */
interface AudienceResolver
{
    /**
     * @param  mixed  $user  The viewer. Null for a guest.
     */
    public function matches(Announcement $announcement, mixed $user): bool;

    /**
     * Everyone who should receive this announcement, for the email delivery
     * path. Return an empty iterable when the audience cannot be enumerated.
     *
     * @return iterable<mixed>
     */
    public function audience(Announcement $announcement): iterable;
}
