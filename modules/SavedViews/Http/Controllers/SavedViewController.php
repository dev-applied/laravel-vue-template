<?php

declare(strict_types=1);

namespace Modules\SavedViews\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\SavedViews\Http\Requests\StoreSavedViewRequest;
use Modules\SavedViews\Http\Requests\UpdateSavedViewRequest;
use Modules\SavedViews\Http\Resources\SavedViewResource;
use Modules\SavedViews\Models\SavedView;
use Modules\SavedViews\Support\SavedViewScope;

class SavedViewController extends Controller
{
    public function __construct(private readonly SavedViewScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['key' => ['required', 'string', 'max:128']]);

        $user = $request->user();

        // The screen check, not just the row check. `key` is a free string
        // from the request, so without this a caller can guess their way to
        // the SHARED views of screens they cannot open.
        abort_unless($this->scope->allows($request->string('key')->toString(), $user), 404);

        $views = SavedView::query()
            ->visibleTo($user, $request->string('key')->toString())
            ->with('user')
            ->tap(fn (Builder $q) => $this->scope->apply($q, $user))
            // Own views before shared ones: your own picker should lead with
            // what you made.
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->getKey()])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json(['views' => SavedViewResource::collection($views)]);
    }

    public function store(StoreSavedViewRequest $request): JsonResponse
    {
        $user = $request->user();

        // Same check as index(), for the sharper case: creating a view marked
        // `is_shared` on a screen you cannot open plants a row into the picker
        // of everyone who CAN open it.
        abort_unless($this->scope->allows((string) $request->validated('key'), $user), 404);

        $view = DB::transaction(function () use ($request, $user) {
            $view = SavedView::create([
                ...$request->validated(),
                ...$this->scope->attributes($user),
                'user_id' => $user->getKey(),
            ]);

            $this->enforceSingleDefault($view, $user);

            return $view;
        });

        return response()->json(new SavedViewResource($view), 201);
    }

    public function update(UpdateSavedViewRequest $request, SavedView $savedView): JsonResponse
    {
        $user = $request->user();

        $this->assertEditable($savedView, $user);

        DB::transaction(function () use ($request, $savedView, $user) {
            $savedView->update($request->validated());
            $this->enforceSingleDefault($savedView, $user);
        });

        return response()->json(new SavedViewResource($savedView->fresh()));
    }

    public function destroy(Request $request, SavedView $savedView): JsonResponse
    {
        $this->assertEditable($savedView, $request->user());

        $savedView->delete();

        return response()->json(['message' => 'View deleted.']);
    }

    /**
     * A shared view someone else owns is read-only: applying it is fine,
     * changing it is not. One person tidying their own picker must not
     * silently rewrite everyone else's.
     */
    private function assertEditable(SavedView $view, mixed $user): void
    {
        if (! $view->isEditableBy($user)) {
            throw new AppException('That view belongs to someone else.', 403);
        }
    }

    /**
     * At most one default per user per screen.
     *
     * Enforced by clearing the others rather than by a unique index: MySQL
     * would need a partial index it does not support, and two defaults means
     * the screen opens on whichever row sorted first — a bug that looks random.
     */
    private function enforceSingleDefault(SavedView $view, mixed $user): void
    {
        if (! $view->is_default) {
            return;
        }

        SavedView::query()
            ->where('user_id', $user->getKey())
            ->where('key', $view->key)
            ->whereKeyNot($view->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
