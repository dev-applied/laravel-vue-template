<?php

declare(strict_types=1);

namespace Modules\GlobalSearch\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Where a project declares what is searchable. Register from
 * AppServiceProvider::boot():
 *
 *   app(SearchRegistry::class)->register(
 *       key:      'items',
 *       label:    'Items',
 *       query:    fn (string $term) => Item::query()->where('name', 'like', "%{$term}%"),
 *       title:    fn (Item $item) => $item->name,
 *       subtitle: fn (Item $item) => $item->owner?->name,
 *       route:    fn (Item $item) => ['name' => 'items.show', 'params' => ['id' => $item->id]],
 *       icon:     'inventory_2',
 *       ability:  'viewAny',
 *   );
 *
 * A registry rather than a `Searchable` trait on each model, for the same
 * reason Exports uses one: the search surface becomes an explicit allow-list.
 * A trait makes every model that ever adopts it searchable by everyone who can
 * reach the endpoint, and the mistake is silent — a new column on an existing
 * searchable model quietly becomes readable through search. Here, nothing is
 * searchable until somebody writes it down.
 */
class SearchRegistry
{
    /** @var array<string, SearchSource> */
    private array $sources = [];

    /**
     * @param  Closure(string): mixed  $query
     * @param  Closure(mixed): string  $title
     */
    public function register(
        string $key,
        string $label,
        Closure $query,
        Closure $title,
        ?Closure $subtitle = null,
        ?Closure $route = null,
        ?string $icon = null,
        ?string $ability = null,
        int $order = 0,
    ): void {
        $this->sources[$key] = new SearchSource(
            $key, $label, $query, $title, $subtitle, $route, $icon, $ability, $order,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function get(string $key): SearchSource
    {
        return $this->sources[$key] ?? throw new RuntimeException("No search source registered for [{$key}].");
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->sources);
    }

    /**
     * The sources this user may search, in declared order.
     *
     * An unauthorised source is omitted rather than returned empty. Returning
     * it empty tells the user the type exists and that they matched nothing in
     * it, which is a different statement and one they are not entitled to.
     *
     * @return array<string, SearchSource>
     */
    public function authorisedFor(?Authenticatable $user): array
    {
        $allowed = array_filter(
            $this->sources,
            fn (SearchSource $source) => $source->ability === null
                || Gate::forUser($user)->allows($source->ability),
        );

        uasort($allowed, fn (SearchSource $a, SearchSource $b) => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $allowed;
    }
}
