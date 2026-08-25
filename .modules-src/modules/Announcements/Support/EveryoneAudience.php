<?php

declare(strict_types=1);

namespace Modules\Announcements\Support;

use App\Models\User;
use Modules\Announcements\Models\Announcement;

/**
 * The default binding: an announcement addressed to `everyone` goes to every
 * authenticated user, and any other audience string matches nobody until the
 * project binds a resolver that understands it.
 *
 * Failing closed matters here. An unknown audience that defaulted to "show it"
 * would broadcast an announcement meant for one group to the whole user base,
 * and there is no un-sending that.
 */
class EveryoneAudience implements AudienceResolver
{
    public function matches(Announcement $announcement, mixed $user): bool
    {
        return $announcement->audience === Announcement::AUDIENCE_EVERYONE && $user !== null;
    }

    public function audience(Announcement $announcement): iterable
    {
        if ($announcement->audience !== Announcement::AUDIENCE_EVERYONE) {
            return [];
        }

        return User::query()->cursor();
    }
}
