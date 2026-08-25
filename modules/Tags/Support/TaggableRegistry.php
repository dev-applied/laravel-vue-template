<?php

declare(strict_types=1);

namespace Modules\Tags\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The allow-list of models that accept tags through the API.
 *
 * Same reasoning as Comments: the endpoint takes a model type from the request,
 * so without this a caller could tag any Eloquent class in the app — and
 * confirm which ids exist while doing it.
 */
class TaggableRegistry
{
    /** @var array<string, array{model: class-string<Model>, ability: string|null}> */
    private array $types = [];

    /**
     * @param  class-string<Model>  $model
     */
    public function register(string $alias, string $model, ?string $ability = 'update'): void
    {
        $this->types[$alias] = ['model' => $model, 'ability' => $ability];
    }

    public function has(string $alias): bool
    {
        return isset($this->types[$alias]);
    }

    /**
     * @return array{model: class-string<Model>, ability: string|null}
     */
    public function get(string $alias): array
    {
        if (! $this->has($alias)) {
            throw new RuntimeException("No taggable type registered as [{$alias}].");
        }

        return $this->types[$alias];
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return array_keys($this->types);
    }
}
