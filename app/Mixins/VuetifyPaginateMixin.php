<?php

namespace App\Mixins;

use App\Traits\WithSelected;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Schema;

/**
 * Class VuetifyPaginateMixin
 * @package App\Mixins
 * @mixin Builder
 */
class VuetifyPaginateMixin
{
    public function vuetifyPaginate(): Closure
    {
        return function (): LengthAwarePaginator {
            $query = $this
                ->when(request()->input('sortBy'), function (Builder $query, array $sortBy) {
                    foreach ($sortBy as $sort) {
                        $table = $this->getModel()->getTable();
                        if (Schema::hasColumn($table, $sort['key'])) {
                            $this->orderBy($sort['key'], $sort['order']);
                        }
                        if ($sort['key'] === 'full_name') {
                            $this->orderBy('first_name', $sort['order']);
                            $this->orderBy('middle_name', $sort['order']);
                            $this->orderBy('last_name', $sort['order']);
                        }
                    }

                })
                ->when(in_array(WithSelected::class, class_uses_recursive($this->getModel())), function (Builder $query) {
                    $query->withSelected(request('selected'));
                });

            if (request()->input('itemsPerPage') === "-1") {
                $items = $query->get();
                return new LengthAwarePaginator($items, $items->count(), ($items->count() > 0 ? $items->count() : 1), 1) ;
            } else {
                return $query->paginate(request()->input('itemsPerPage', 10), ['*']);
            }
        };
    }
}
