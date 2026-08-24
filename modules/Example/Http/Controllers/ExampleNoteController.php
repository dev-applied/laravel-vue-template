<?php

declare(strict_types=1);

namespace Modules\Example\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Example\Http\Requests\StoreExampleNoteRequest;
use Modules\Example\Http\Resources\ExampleNoteResource;
use Modules\Example\Models\ExampleNote;

/**
 * Thin controller per the template's canonical Item pattern — a module
 * controller extends the app's base Controller (part of the kernel contract,
 * see docs/modules.md) and looks exactly like an app controller otherwise.
 */
class ExampleNoteController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ExampleNoteResource::collection(
            ExampleNote::query()->latest('id')->limit(50)->get()
        );
    }

    public function store(StoreExampleNoteRequest $request): ExampleNoteResource
    {
        return new ExampleNoteResource(
            ExampleNote::create($request->validated())
        );
    }
}
