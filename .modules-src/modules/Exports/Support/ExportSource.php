<?php

declare(strict_types=1);

namespace Modules\Exports\Support;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * One registered, exportable listing.
 *
 * `query` is resolved fresh inside the queued job — never a prebuilt builder,
 * which would not survive serialisation. It receives the filter array the user
 * had applied when they hit Export, so the file matches what they were looking
 * at rather than the whole table.
 */
class ExportSource
{
    /**
     * @param  array<string, string>  $columns  header label keyed by attribute path
     * @param  Closure(array<string, mixed>): Builder<*>  $query
     * @param  Closure(mixed, string): mixed|null  $format  optional per-cell formatter
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $columns,
        public readonly Closure $query,
        public readonly ?Closure $format = null,
        public readonly ?string $ability = null,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function resolveQuery(array $filters): Builder
    {
        return ($this->query)($filters);
    }

    public function cell(mixed $value, string $column): mixed
    {
        return $this->format ? ($this->format)($value, $column) : $value;
    }
}
