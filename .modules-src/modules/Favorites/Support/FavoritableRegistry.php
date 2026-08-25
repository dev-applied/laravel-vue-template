<?php

declare(strict_types=1);

namespace Modules\Favorites\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The allow-list of models that can be favourited.
 *
 * The endpoint takes a model type from the request, so without this a caller
 * could star any Eloquent class in the app — including ones whose ids they
 * should not be able to confirm exist.
 *
 *   $registry->register('article', Article::class, ability: 'view');
 *
 * The `ability` is not decoration. A favourite is readable back: the list
 * endpoint returns a label for each starred record. Without an authorization
 * check, starring is a way to read the title of anything you can name, and
 * un-starring tells you whether it existed. It defaults to 'view' for that
 * reason, and a project passes null only where the model genuinely has no
 * per-record visibility.
 *
 * Same shape as CommentableRegistry and TaggableRegistry — this is the pattern
 * now, not a one-off.
 */
class FavoritableRegistry
{
    /** @var array<string, array{model: class-string<Model>, ability: string|null}> */
    private array $types = [];

    /** @param class-string<Model> $model */
    public function register(string $alias, string $model, ?string $ability = 'view'): void
    {
        $this->types[mb_strtolower($alias)] = ['model' => $model, 'ability' => $ability];
    }

    public function has(string $alias): bool
    {
        return isset($this->types[mb_strtolower($alias)]);
    }

    /** @return array{model: class-string<Model>, ability: string|null} */
    public function get(string $alias): array
    {
        if (! $this->has($alias)) {
            throw new RuntimeException("No favoritable type registered as [{$alias}].");
        }

        return $this->types[mb_strtolower($alias)];
    }

    /** The alias for a record, for serialising back to a client. */
    public function aliasFor(Model $model): ?string
    {
        return $this->aliasForType($model::class);
    }

    /**
     * The alias for a stored morph class.
     *
     * Takes the class NAME rather than an instance, because a favourite
     * outlives a hard-deleted target: the row still knows what it pointed at,
     * but the relation resolves to null. Reading the alias off the instance
     * meant one deleted record 500'd the entire favourites list.
     */
    public function aliasForType(string $class): ?string
    {
        foreach ($this->types as $alias => $type) {
            if ($class === $type['model'] || is_subclass_of($class, $type['model'])) {
                return $alias;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function aliases(): array
    {
        return array_keys($this->types);
    }
}
