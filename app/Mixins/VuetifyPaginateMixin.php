<?php

declare(strict_types=1);

namespace App\Mixins;

use App\Interfaces\WithSelected;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Class VuetifyPaginateMixin
 *
 * @mixin Builder|QueryBuilder
 */
class VuetifyPaginateMixin
{
    public function vuetifyPaginate(): Closure
    {
        return function (): LengthAwarePaginator {
            return $this
                ->when(request()->input('sortBy'), fn (Builder|QueryBuilder $q, $sortBy) => $q->applySorting($sortBy))
                ->when(request()->input('excludeValues'), fn ($q, $values) => $q->excludeValues($values, request()->input('key', 'id')))
                ->handleSelectedItems()
                ->paginateResults();
        };
    }

    /**
     * Apply sorting to the query based on sortBy parameter
     */
    public function applySorting(): Closure
    {
        return function (array $sortBy): static {
            $rawColumns = $this->getQuery()->columns ?: [];
            $columns    = collect($rawColumns);

            if ($columns->isEmpty()) {
                $tableName = $this->resolveTable();
                $columns   = collect($tableName ? Schema::getColumnListing($tableName) : []);
            } else {
                $columns = $columns->map(function ($column) {
                    if (! is_string($column)) {
                        return null;
                    }

                    if (str_contains($column, '.*')) {
                        [$table] = explode('.', $column, 2);

                        return Schema::getColumnListing($table);
                    }

                    if (str_contains(mb_strtolower($column), ' as ')) {
                        [$select, $alias] = preg_split('/\s+as\s+/i', $column);

                        return str_replace('`', '', $alias);
                    }

                    return str_replace('`', '', $column);
                })
                    ->flatten()
                    ->filter();
            }

            foreach ($sortBy as $sort) {
                if ($sort['key'] === 'full_name') {
                    $this->orderBy('first_name', $sort['order']);
                    $this->orderBy('last_name', $sort['order']);
                } elseif ($columns->contains($sort['key'])) {
                    $this->orderBy($sort['key'], $sort['order']);
                }
            }

            return $this;
        };
    }

    /**
     * Exclude specific IDs from the query
     */
    public function excludeValues(): Closure
    {
        return function (array $values, string $key): static {
            $table  = $this->resolveTable();
            $column = ($table ? "$table.$key" : $key);

            return $this->whereNotIn($column, $values);
        };
    }

    /**
     * Handle selected items prioritization
     */
    public function handleSelectedItems(): Closure
    {
        return function (): static {
            $selected = request()->input('selected', []);
            $key      = request()->input('key', 'id');

            // If model implements WithSelected interface, delegate to that scope
            if ($this->getModel() && $this->getModel() instanceof WithSelected) {
                return $this->withSelected($selected, $key);
            }

            // Normalize selected to array
            if (! is_array($selected)) {
                $selected = [$selected];
            }
            $selected = collect($selected)->filter();

            if ($selected->isEmpty()) {
                return $this;
            }

            $table = $this->resolveTable();

            if (! $table) {
                throw new RuntimeException('Unable to resolve table name for the query. SQL: '.$this->toRawSql());
            }

            // Store original orders to reapply later
            $originalOrders           = $this->getQuery()->orders ?? [];
            $this->getQuery()->orders = null;

            // Ensure we're selecting all columns from the table
            if (! $this->getQuery()->columns) {
                $this->select("{$table}.*");
            }

            // Add priority column (1 for selected, 2 for others)
            $this->selectRaw("CASE WHEN {$table}.{$key} IN (".$selected->implode(',').') THEN 1 ELSE 2 END as __priority');

            // Conditionally filter soft deletes:
            // - Include soft deleted rows ONLY if they are in the selected list
            // - Exclude soft deleted rows if they are NOT in the selected list
            $hasSoftDeletes = $this->getModel() && in_array(SoftDeletes::class,
                class_uses_recursive($this->getModel()));

            if ($hasSoftDeletes) {
                $this->getModel()->withTrashed();
                $deletedAtColumn = $this->getModel()->getQualifiedDeletedAtColumn();
                $this->where(function ($q) use ($table, $deletedAtColumn) {
                    // Either not soft deleted
                    $q
                        ->whereNull("{$table}.{$deletedAtColumn}")
                        // OR is selected (in which case priority will be 1, not 2)
                        ->orWhereRaw('selected_items.priority = 1');
                });
            }

            // Order by priority first (selected items first)
            if (count($this->getQuery()->wheres) > 0) {
                $this->orWhere(function ($q) use ($table, $selected, $key) {
                    $q->whereIn("{$table}.{$key}", $selected);
                });
            }
            $this->orderBy('__priority');

            // Then apply original orders
            foreach ($originalOrders as $order) {
                if (isset($order['type']) && $order['type'] === 'Raw') {
                    $this->orderByRaw($order['sql']);
                } else {
                    $colParts = explode('.', $order['column']);
                    $this->orderBy(last($colParts), $order['direction']);
                }
            }

            return $this;
        };
    }

    /**
     * Paginate the results based on itemsPerPage parameter
     */
    public function paginateResults(): Closure
    {
        return function (): LengthAwarePaginator {
            if (request()->input('itemsPerPage') === '-1') {
                $items = $this->get();

                return new LengthAwarePaginator(
                    $items,
                    $items->count(),
                    $items->count() > 0 ? $items->count() : 1,
                    1
                );
            }

            $perPage = max(
                count(request('selected', [])) + 10,
                request()->input('itemsPerPage', 10)
            );

            $log = $this->toRawSql();

            return $this->paginate($perPage);
        };
    }

    /**
     * Resolve table name from either Eloquent or Query builder
     */
    public function resolveTable(): Closure
    {
        return function (): ?string {
            if ($this instanceof Builder) {
                return $this->getModel()?->getTable();
            }

            if ($this instanceof QueryBuilder) {
                return $this->from ?? null;
            }

            return null;
        };
    }
}
