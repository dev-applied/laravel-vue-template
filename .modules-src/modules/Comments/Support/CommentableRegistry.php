<?php

declare(strict_types=1);

namespace Modules\Comments\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The allow-list of models that accept comments.
 *
 * The endpoint takes a model type from the request, so without this a caller
 * could attach a comment to any Eloquent class in the app — including ones
 * whose ids they should not be able to confirm exist. A project registers what
 * is commentable, and anything unregistered is rejected outright.
 *
 *   $registry->register('order', Order::class, ability: 'view');
 */
class CommentableRegistry
{
    /** @var array<string, array{model: class-string<Model>, ability: string|null}> */
    private array $types = [];

    /**
     * @param  class-string<Model>  $model
     * @param  string|null  $ability  Checked against the resolved record before
     *                                any comment on it is read or written.
     */
    public function register(string $alias, string $model, ?string $ability = 'view'): void
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
            throw new RuntimeException("No commentable type registered as [{$alias}].");
        }

        return $this->types[$alias];
    }

    /**
     * Find the registration a stored comment points at.
     *
     * A Comment records `commentable_type` — the MORPH class, which is the
     * alias when the app sets a morph map and the FQCN otherwise. Comparing
     * against a freshly-made instance's getMorphClass() gets both right
     * without the registry needing to know which convention the app uses.
     *
     * @return array{model: class-string<Model>, ability: string|null}|null
     */
    public function forMorphClass(string $morphClass): ?array
    {
        foreach ($this->types as $type) {
            /** @var Model $probe */
            $probe = new $type['model'];

            if ($probe->getMorphClass() === $morphClass || $type['model'] === $morphClass) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return array_keys($this->types);
    }
}
