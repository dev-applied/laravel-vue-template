<?php

declare(strict_types=1);

namespace Modules\Favorites\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Favorites\Http\Resources\FavoriteResource;
use Modules\Favorites\Models\Favorite;
use Modules\Favorites\Support\FavoritableRegistry;

/**
 * Favourites are per-user by definition, so every query here is scoped to
 * `$request->user()` and none of them takes a user id from the caller. There is
 * deliberately no admin surface: "what has this person starred" is not an
 * administrative question, and adding the endpoint would make it one.
 */
class FavoriteController extends Controller
{
    public function __construct(private readonly FavoritableRegistry $registry) {}

    /** The current user's favourites, newest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = Favorite::query()
            ->where('user_id', $request->user()->getKey())
            ->when(
                $request->filled('type') && $this->registry->has((string) $request->query('type')),
                fn ($q) => $q->where('favoritable_type', $this->registry->get((string) $request->query('type'))['model'])
            )
            // morphWith would need a per-type map; a plain eager load is enough
            // and keeps a deleted target as a null relation rather than a crash.
            ->with('favoritable')
            ->latest()
            ->paginate((int) $request->integer('per_page', 25));

        return FavoriteResource::collection($favorites);
    }

    /**
     * Toggle, rather than separate add/remove.
     *
     * A star is a two-state control and the client should not have to know
     * which state it is in to change it — that read-then-write is exactly the
     * race that leaves a double-tap in the wrong state.
     */
    public function toggle(Request $request, string $type, string $id): JsonResponse
    {
        $record = $this->resolve($request, $type, $id);

        $attributes = [
            'user_id'          => $request->user()->getKey(),
            'favoritable_type' => $record->getMorphClass(),
            'favoritable_id'   => $record->getKey(),
        ];

        $existing = Favorite::query()->where($attributes)->first();

        if ($existing !== null) {
            $existing->delete();

            return response()->json(['favorited' => false]);
        }

        try {
            Favorite::query()->create($attributes);
        } catch (QueryException $e) {
            // Two tabs, one star. The unique index is what actually prevents
            // the duplicate; losing that race means the favourite now exists,
            // which is the state the caller asked for either way.
            if (! $this->isDuplicate($e)) {
                throw $e;
            }
        }

        return response()->json(['favorited' => true]);
    }

    /**
     * Explicit un-favourite.
     *
     * Idempotent on purpose: a client retrying a delete it already made should
     * get the same answer, not a 404 that reads like the record vanished.
     */
    public function destroy(Request $request, string $type, string $id): JsonResponse
    {
        $record = $this->resolve($request, $type, $id);

        Favorite::query()
            ->where('user_id', $request->user()->getKey())
            ->where('favoritable_type', $record->getMorphClass())
            ->where('favoritable_id', $record->getKey())
            ->delete();

        return response()->json(['favorited' => false]);
    }

    /**
     * Resolve an allow-listed type + id, or 404.
     *
     * 404 rather than 403 for an unregistered type: which models this app
     * happens to have is not something an unauthorised caller should be able to
     * map by probing, and "not favouritable" and "does not exist" are the same
     * answer from outside.
     */
    protected function resolve(Request $request, string $type, string $id)
    {
        abort_unless($this->registry->has($type), 404);

        $registered = $this->registry->get($type);
        $record     = $registered['model']::query()->findOrFail($id);

        // Starring is readable back — the list endpoint returns a label for
        // each starred record — so without this, favouriting is a way to read
        // the title of anything you can name.
        //
        // 404 and not 403, for the same reason the unregistered-type check
        // above is a 404: a 403 confirms the record exists. findOrFail() has
        // already answered 404 for a missing one, so the two are now
        // indistinguishable from outside.
        if ($registered['ability'] !== null) {
            $request->user()->can($registered['ability'], $record) || abort(404);
        }

        return $record;
    }

    protected function isDuplicate(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            || str_contains(mb_strtolower($e->getMessage()), 'unique');
    }
}
