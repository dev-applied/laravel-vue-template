<?php

declare(strict_types=1);

namespace Modules\GlobalSearch\Support;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One registered, searchable thing.
 *
 * `query` receives the raw term and returns a builder. It is a closure rather
 * than a model class plus a column list because the interesting sources are
 * never a flat LIKE: a project searching orders wants the customer's name too,
 * which is a join the module cannot guess. Handing over the builder means the
 * project owns its own definition of "matches".
 *
 * `route` returns whatever the frontend router needs to open the result. It is
 * deliberately untyped here — the kernel's router DSL takes a name plus params,
 * and a module has no business knowing a project's route names.
 */
class SearchSource
{
    /**
     * @param  Closure(string): Builder<*>  $query
     * @param  Closure(Model): string  $title
     * @param  Closure(Model): ?string|null  $subtitle
     * @param  Closure(Model): array<string, mixed>|null  $route
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly Closure $query,
        public readonly Closure $title,
        public readonly ?Closure $subtitle = null,
        public readonly ?Closure $route = null,
        public readonly ?string $icon = null,
        public readonly ?string $ability = null,
        public readonly int $order = 0,
    ) {}

    public function resolveQuery(string $term): Builder
    {
        return ($this->query)($term);
    }

    /** @return array<string, mixed> */
    public function present(Model $model): array
    {
        return [
            'id'        => $model->getKey(),
            'type'      => $this->key,
            'typeLabel' => $this->label,
            'icon'      => $this->icon,
            'title'     => ($this->title)($model),
            'subtitle'  => $this->subtitle ? ($this->subtitle)($model) : null,
            'route'     => $this->route ? ($this->route)($model) : null,
        ];
    }
}
