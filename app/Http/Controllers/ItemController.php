<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canonical thin controller — delegates validation to FormRequests, response
 * shape to ItemResource, and query filtering to the model's scopeFilter().
 * Every new resource on this stack should look like this.
 *
 * Per-record authorization is delegated to App\Policies\ItemPolicy via
 * `authorizeResource()`, which inserts the matching policy check
 * (viewAny/view/create/update/delete) before each action runs.
 */
class ItemController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Item::class, 'item');
    }

    public function index(Request $request): JsonResponse
    {
        // Explicit whitelist — never forward `$request->all()` into scopeFilter
        // since other query params (`page`, `sortBy`, etc.) share that namespace.
        // Eager-load owner so the list view's owner column doesn't N+1.
        $items = Item::query()
            ->with('owner')
            ->filter($request->only(['status', 'owner_id', 'search']))
            ->latest('id')
            ->vuetifyPaginate();

        // ->additional() preserves the vuetifyPaginate envelope while wrapping
        // each row in ItemResource — frontend keeps its pagination contract.
        $items->setCollection(
            $items->getCollection()->map(fn (Item $item) => new ItemResource($item))->collect()
        );

        return response()->json($items);
    }

    public function show(Item $item): ItemResource
    {
        $item->load('owner');

        return new ItemResource($item);
    }

    public function store(StoreItemRequest $request): ItemResource
    {
        $item = Item::create($request->validated());
        $item->load('owner');

        return new ItemResource($item);
    }

    public function update(UpdateItemRequest $request, Item $item): ItemResource
    {
        $item->update($request->validated());
        $item->load('owner');

        return new ItemResource($item);
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json(null, 204);
    }
}
