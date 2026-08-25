<?php

declare(strict_types=1);

namespace Modules\Tags\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Tags\Models\Tag;
use Modules\Tags\Support\TagPoolScope;

/**
 * The tag pool — what the autocomplete offers and what a filter lists.
 */
class TagController extends Controller
{
    public function __construct(private readonly TagPoolScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'   => ['nullable', 'string', 'max:64'],
            'search' => ['nullable', 'string', 'max:60'],
        ]);

        $type = $request->input('type');

        // The per-record endpoints run the project's registered ability; this
        // one had no gate at all, so any authenticated user could read back
        // every tag name in any pool — and tag names carry exactly the internal
        // judgement a project does not publish (at-risk, legal-hold, vip), with
        // a usage_count next to each one.
        //
        // 404 rather than 403, for the same reason the record endpoints use it:
        // a refusal that differs from "there is nothing here" tells the caller
        // which pools exist.
        if (! $this->scope->allows($type, $request->user())) {
            throw new AppException('Record not found.', 404);
        }

        $tags = Tag::query()
            ->tap(fn ($query) => $this->scope->apply($query, $request->user()))
            ->ofType($type)
            ->search($request->input('search'))
            // Most-used first: the tag someone wants is usually one they have
            // used before. Name breaks the tie so the list is stable.
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'slug', 'color', 'type', 'usage_count']);

        return response()->json(['tags' => $tags]);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:24'],
        ]);

        if (isset($data['name'])) {
            $slug = Tag::slugFor($data['name'], $tag->type);

            // Renaming onto an existing tag is a merge request, not a rename —
            // silently colliding would break the unique index or, worse,
            // orphan every record on one of them.
            if ($slug !== $tag->slug && Tag::where('slug', $slug)->exists()) {
                throw new AppException('A tag with that name already exists. Merge them instead.', 422);
            }

            $data['slug'] = $slug;
        }

        $tag->update($data);

        return response()->json($tag->fresh());
    }

    public function destroy(Tag $tag): JsonResponse
    {
        // The taggables rows cascade; the records themselves are untouched.
        $tag->delete();

        return response()->json(['message' => 'Tag deleted.']);
    }

    /**
     * Fold one tag into another, keeping every record that carried either.
     */
    public function merge(Request $request, Tag $tag): JsonResponse
    {
        $request->validate(['into' => ['required', 'integer', 'exists:tags,id']]);

        $into = Tag::findOrFail($request->integer('into'));

        if ($into->is($tag)) {
            throw new AppException('A tag cannot be merged into itself.', 422);
        }

        $moved = 0;

        foreach (DB::table('taggables')->where('tag_id', $tag->getKey())->get() as $row) {
            // insertOrIgnore, because a record carrying BOTH tags would
            // otherwise violate the unique index halfway through the merge and
            // leave it half-done.
            $moved += DB::table('taggables')->insertOrIgnore([
                'tag_id'        => $into->getKey(),
                'taggable_type' => $row->taggable_type,
                'taggable_id'   => $row->taggable_id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        $tag->delete();

        $into->update(['usage_count' => DB::table('taggables')->where('tag_id', $into->getKey())->count()]);

        return response()->json([
            'message' => 'Tags merged.',
            'moved'   => $moved,
            'tag'     => $into->fresh(),
        ]);
    }
}
