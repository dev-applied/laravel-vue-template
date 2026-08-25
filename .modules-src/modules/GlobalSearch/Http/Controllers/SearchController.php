<?php

declare(strict_types=1);

namespace Modules\GlobalSearch\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GlobalSearch\Http\Requests\SearchRequest;
use Modules\GlobalSearch\Support\SearchRegistry;

class SearchController extends Controller
{
    public function __construct(private readonly SearchRegistry $registry) {}

    /**
     * Search every source this user may reach, grouped by type.
     *
     * Grouped rather than one interleaved list, because ranking across sources
     * is not a thing this module can do honestly: "3 of 40 items" and "1 of 1
     * user" carry no comparable score, and inventing one would put whichever
     * source happened to be registered first at the top forever.
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $term   = mb_trim((string) $request->string('q'));
        $limit  = (int) $request->integer('limit', 5);
        $wanted = $request->array('types');

        $sources = $this->registry->authorisedFor($request->user());

        if ($wanted !== []) {
            $sources = array_intersect_key($sources, array_flip($wanted));
        }

        $groups = [];

        foreach ($sources as $key => $source) {
            // One extra row, then sliced off: that is how `hasMore` is known
            // without a second COUNT query per source per keystroke.
            $rows    = $source->resolveQuery($term)->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;

            $results = $rows->take($limit)
                ->map(fn ($model) => $source->present($model))
                ->values()
                ->all();

            if ($results === []) {
                continue;
            }

            $groups[] = [
                'type'    => $key,
                'label'   => $source->label,
                'icon'    => $source->icon,
                'hasMore' => $hasMore,
                'results' => $results,
            ];
        }

        return response()->json([
            'data' => [
                'query'  => $term,
                'groups' => $groups,
                'total'  => array_sum(array_map(fn (array $g) => count($g['results']), $groups)),
            ],
        ]);
    }

    /**
     * The types this user may search — what the palette shows as filter chips
     * before anyone has typed anything.
     */
    public function types(Request $request): JsonResponse
    {
        $sources = $this->registry->authorisedFor($request->user());

        return response()->json([
            'data' => array_values(array_map(fn ($source) => [
                'type'  => $source->key,
                'label' => $source->label,
                'icon'  => $source->icon,
            ], $sources)),
        ]);
    }
}
