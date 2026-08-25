<?php

declare(strict_types=1);

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Announcements\Http\Resources\AnnouncementResource;
use Modules\Announcements\Models\Announcement;

class PublishAnnouncementController extends Controller
{
    public function publish(Announcement $announcement): JsonResponse
    {
        // Publishing twice must not re-send the email or reset the clock — the
        // second click on a slow button is the normal case, not the edge one.
        //
        // Reading published_at and then writing it is not enough for that: two
        // clicks that arrive together both see null, both write, and both queue
        // the mail job, so the whole audience is emailed twice. The condition
        // belongs on the UPDATE, where exactly one of them can win it.
        $claimed = Announcement::query()
            ->whereKey($announcement->getKey())
            ->whereNull('published_at')
            ->update(['published_at' => now()]);

        if ($claimed === 1
            && class_exists(\Modules\Announcements\Jobs\SendAnnouncementEmails::class)
            && config('announcements.email', false)) {
            \Modules\Announcements\Jobs\SendAnnouncementEmails::dispatch($announcement->id);
        }

        return response()->json(new AnnouncementResource($announcement->fresh()));
    }

    public function unpublish(Announcement $announcement): JsonResponse
    {
        // Pulling a wrong announcement down has to be instant, so this clears
        // published_at outright rather than setting ends_at to now.
        $announcement->update(['published_at' => null]);

        return response()->json(new AnnouncementResource($announcement->fresh()));
    }
}
