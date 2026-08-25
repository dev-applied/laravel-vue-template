<?php

declare(strict_types=1);

namespace Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Announcements\Http\Requests\StoreAnnouncementRequest;
use Modules\Announcements\Http\Resources\AnnouncementResource;
use Modules\Announcements\Models\Announcement;

/**
 * Authoring side. Gated by the `manage-announcements` ability on the route —
 * this endpoint writes something every user will see.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $announcements = Announcement::query()
            ->withCount('dismissals')
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->string('status')->toString()) {
                    'live'      => $query->live(),
                    'draft'     => $query->whereNull('published_at'),
                    'scheduled' => $query->whereNotNull('published_at')->where('starts_at', '>', now()),
                    'expired'   => $query->whereNotNull('ends_at')->where('ends_at', '<=', now()),
                    default     => null,
                };
            })
            ->latest('id')
            ->vuetifyPaginate();

        $announcements->setCollection(
            $announcements->getCollection()
                ->map(fn (Announcement $a) => new AnnouncementResource($a))
                ->collect()
        );

        return response()->json($announcements);
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $announcement = Announcement::create($request->validated());

        return response()->json(new AnnouncementResource($announcement), 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json(new AnnouncementResource($announcement->loadCount('dismissals')));
    }

    public function update(StoreAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $announcement->update($request->validated());

        return response()->json(new AnnouncementResource($announcement));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }
}
