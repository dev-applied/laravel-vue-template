<?php

declare(strict_types=1);

namespace App\Mixins;

use App\Traits\WithSelected;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Schema;

/**
 * Class VuetifyPaginateMixin
 *
 * @mixin Builder
 */
class VuetifyPaginateMixin
{
    public function vuetifyPaginate(): Closure
    {
        return function (): LengthAwarePaginator {
            $query = $this
                ->when(request()->input('sortBy'), function (Builder $query, array $sortBy) {
                    $columns = collect($query->getQuery()->getColumns());

                    if ($columns->isEmpty()) {
                        $tableName = $query->getModel()->getTable();
                        $columns   = collect(Schema::getColumnListing($tableName))
                            ->map(function ($col) {
                                return $col;
                            });
                    } else {
                        $columns = $columns->map(function ($column) {
                            if (str_contains($column, '.*')) {
                                [$table] = explode('.', $column);

                                return collect(Schema::getColumns($table))->pluck('name');
                            }

                            if (str_contains($column, ' as ')) {
                                [$select, $column] = explode(' as ', $column);

                                return str_replace('`', '', $column);
                            }

                            return $column;
                        })
                            ->flatten();
                    }

                    foreach ($sortBy as $sort) {
                        if ($sort['key'] === 'full_name') {
                            $query->orderBy('first_name', $sort['order']);
                            $query->orderBy('last_name', $sort['order']);
                        } else if ($columns->contains($sort['key'])) {
                            $query->orderBy($sort['key'], $sort['order']);
                        }
                    }
                })
                ->when(request()->input('excludeIds'), function (Builder $query, array $excludeIds) {
                    $query->whereNotIn("{$query->getModel()->getTable()}.id", $excludeIds);
                })
                ->when(in_array(WithSelected::class, class_uses_recursive($this->getModel())), function (Builder $query) {
                    $query->withSelected(request('selected'), request('key'));
                });

            if (request()->input('itemsPerPage') === '-1') {
                $items = $query->get();

                return new LengthAwarePaginator($items, $items->count(), ($items->count() > 0 ? $items->count() : 1), 1);
            } else {
                return $query->paginate(request()->input('itemsPerPage', 10), ['*']);
            }
        };
    }
}
