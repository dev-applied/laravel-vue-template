<?php

declare(strict_types=1);

namespace Modules\GlobalSearch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GlobalSearch\Models\SearchHistory;

class SearchHistoryController extends Controller
{
    /** The current user's recent searches, newest first. */
    public function index(Request $request): JsonResponse
    {
        $entries = SearchHistory::query()
            ->recentFor($request->user()->getKey())
            ->limit(min((int) $request->integer('limit', 8), 25))
            ->get(['id', 'term', 'result_count', 'updated_at']);

        return response()->json([
            'data' => $entries->map(fn (SearchHistory $entry) => [
                'id'          => $entry->id,
                'term'        => $entry->term,
                'resultCount' => $entry->result_count,
                'searchedAt'  => $entry->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Record a search. Called on SUBMIT, never on keystroke — see
     * SearchHistory::remember() for why the distinction matters.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'term'         => ['required', 'string', 'min:2', 'max:255'],
            'result_count' => ['sometimes', 'integer', 'min:0'],
        ]);

        SearchHistory::remember(
            $request->user()->getKey(),
            $data['term'],
            (int) ($data['result_count'] ?? 0),
        );

        return response()->json(status: 204);
    }

    /**
     * Forget one entry, or the lot.
     *
     * Scoped to the caller's own rows on both paths — a history row is a record
     * of what somebody went looking for, which is worth as much as the results
     * were. There is no admin view of this table and there should not be one.
     */
    public function destroy(Request $request, ?SearchHistory $history = null): JsonResponse
    {
        $userId = $request->user()->getKey();

        if ($history === null) {
            SearchHistory::query()->where('user_id', $userId)->delete();

            return response()->json(status: 204);
        }

        abort_unless($history->user_id === $userId, 404);

        $history->delete();

        return response()->json(status: 204);
    }
}
