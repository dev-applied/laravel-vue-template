<?php

declare(strict_types=1);

namespace Modules\Tags\Http\Controllers;

use App\Exceptions\AppException;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Tags\Support\TaggableRegistry;

/**
 * Tags on one record.
 */
class TaggableController extends Controller
{
    public function __construct(private readonly TaggableRegistry $registry) {}

    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $record = $this->resolve($request, $type, $id);

        return response()->json(['tags' => $record->tags()->orderBy('name')->get()]);
    }

    public function sync(Request $request, string $type, int $id): JsonResponse
    {
        $request->validate([
            'tags'   => ['present', 'array', 'max:50'],
            'tags.*' => ['string', 'max:60'],
        ]);

        $record = $this->resolve($request, $type, $id);

        $record->syncTags($request->input('tags', []));

        return response()->json(['tags' => $record->tags()->orderBy('name')->get()]);
    }

    private function resolve(Request $request, string $type, int $id): Model
    {
        if (! $this->registry->has($type)) {
            throw new AppException("Tagging is not enabled for [{$type}].", 404);
        }

        ['model' => $model, 'ability' => $ability] = $this->registry->get($type);

        $record = $model::query()->find($id);

        if ($record === null) {
            throw new AppException('Record not found.', 404);
        }

        // Reading and writing tags both run the same ability: a tag list can
        // leak how a record has been categorised internally.
        if ($ability !== null && ! Gate::forUser($request->user())->allows($ability, $record)) {
            // Same message and status as a missing record. A 403 confirms the
            // record exists, and with sequential ids that turns this endpoint
            // into a table census.
            throw new AppException('Record not found.', 404);
        }

        return $record;
    }
}
