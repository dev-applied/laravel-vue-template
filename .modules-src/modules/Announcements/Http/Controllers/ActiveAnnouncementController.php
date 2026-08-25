<?php

declare(strict_types=1);

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Announcements\Http\Resources\AnnouncementResource;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Models\AnnouncementDismissal;
use Modules\Announcements\Support\AudienceResolver;

/**
 * Reader side. Every authenticated user hits this on app boot, so it stays
 * cheap: one indexed query, then an in-PHP audience filter over what is
 * already a short list.
 */
class ActiveAnnouncementController extends Controller
{
    public function index(Request $request, AudienceResolver $resolver): JsonResponse
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->live()
            ->unresolvedFor($user)
            // Loudest first: an error banner should not sit under an info one.
            // A CASE rather than MySQL's FIELD() — projects run sqlite in tests
            // and postgres in a couple of places, and FIELD() exists on neither.
            ->orderByRaw("CASE level WHEN 'error' THEN 0 WHEN 'warning' THEN 1 WHEN 'success' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get()
            ->filter(fn (Announcement $a) => $resolver->matches($a, $user))
            ->values();

        return response()->json([
            'announcements' => AnnouncementResource::collection($announcements),
        ]);
    }

    public function dismiss(Request $request, Announcement $announcement, AudienceResolver $resolver): JsonResponse
    {
        $user = $request->user();

        // The same two filters index() applies, and they were missing here.
        // Without them any authenticated user could dismiss — and, worse,
        // ACKNOWLEDGE — a draft, or an announcement addressed to someone else.
        // `requires_acknowledgement` exists so an organisation can show that a
        // particular person was shown a particular policy; a record of someone
        // acknowledging a notice that was never displayed to them is worse than
        // no record, because it reads as evidence.
        //
        // 404 rather than 403: the two answers together would tell an
        // unauthenticated-ish caller which unpublished announcements exist and
        // what their flags are, one id at a time.
        abort_unless($announcement->isLive() && $resolver->matches($announcement, $user), 404);

        if (! $announcement->dismissible && ! $announcement->requires_acknowledgement) {
            return response()->json(['message' => 'This announcement cannot be dismissed.'], 422);
        }

        // updateOrCreate against the unique index: a double-click writes one
        // row, not two, so dismissal counts stay honest.
        $dismissal = AnnouncementDismissal::firstOrNew([
            'announcement_id' => $announcement->getKey(),
            'user_id'         => $user->getKey(),
        ]);

        // Stamped once and never restamped. `['acknowledged_at' => now()]` in
        // an update payload rewrites it on every call, so a second dismiss —
        // another tab, a double-click, a re-read months later — silently moved
        // the record to a later time than the person actually accepted. The
        // column's whole purpose is to say WHEN.
        if ($announcement->requires_acknowledgement && $dismissal->acknowledged_at === null) {
            $dismissal->acknowledged_at = now();
        }

        $dismissal->save();

        return response()->json(['message' => 'Dismissed.']);
    }
}
