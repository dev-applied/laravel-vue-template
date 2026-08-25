<?php

declare(strict_types=1);

namespace Modules\Checklists\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * What a checklist may be attached to.
 *
 * An allow-list, for the same reason Exports and GlobalSearch use one: the
 * instantiate endpoint takes a subject type off the wire, and without a
 * registry that is an arbitrary-model-lookup endpoint. A project declares what
 * is inspectable:
 *
 *   app(ChecklistSubjects::class)->register('vehicle', Vehicle::class);
 *
 * The KEY is what travels, never the class name. A client sending
 * `App\Models\User` and having it resolved is how an allow-list turns back into
 * the thing it was meant to replace.
 */
class ChecklistSubjects
{
    /** @var array<string, class-string<Model>> */
    private array $types = [];

    /** @param  class-string<Model>  $model */
    public function register(string $key, string $model): void
    {
        $this->types[$key] = $model;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /** @return class-string<Model> */
    public function modelFor(string $key): string
    {
        return $this->types[$key] ?? throw new RuntimeException("No checklist subject registered for [{$key}].");
    }

    public function resolve(string $key, int|string $id): Model
    {
        $model = $this->modelFor($key);

        return $model::query()->findOrFail($id);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }
}
