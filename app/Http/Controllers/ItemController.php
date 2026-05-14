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
 */
class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Item::query()
            ->filter($request->all())
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
