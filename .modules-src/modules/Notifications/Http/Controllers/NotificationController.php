<?php

declare(strict_types=1);

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Notifications\Http\Resources\NotificationResource;
use Modules\Notifications\Models\Notification;

/**
 * Every action is scoped to the authenticated notifiable, so there is no policy
 * to attach — a user simply cannot address another user's rows. Thin otherwise,
 * matching the template's canonical controller shape.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request);

        if ($request->boolean('unread')) {
            $query->unread();
        }

        $notifications = $query->latest('created_at')->vuetifyPaginate();

        $notifications->setCollection(
            $notifications->getCollection()
                ->map(fn (Notification $n) => new NotificationResource($n))
                ->collect()
        );

        return response()->json($notifications);
    }

    /** Cheap poll target for the bell — a count, never a payload. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->scoped($request)->unread()->count()]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = $this->scoped($request)->findOrFail($notification);
        $model->markAsRead();

        return response()->json(['notification' => new NotificationResource($model->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->scoped($request)->unread()->update(['read_at' => now()]);

        return response()->json(['count' => 0]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->scoped($request)->findOrFail($notification)->delete();

        return response()->json()->setStatusCode(204);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Notification> */
    private function scoped(Request $request)
    {
        $user = $request->user();

        return Notification::query()->forNotifiable($user::class, $user->getKey());
    }
}
