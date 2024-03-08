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
                ->when(request()->input('sort'), function (Builder $query, string $sort) {
                    if (Schema::hasColumn($this->getModel()->getTable(), $sort)) {
                        $this->orderBy($sort, request()->input('direction'));
                    }
                })
                ->when(in_array(WithSelected::class, class_uses_recursive($this->getModel())), function (Builder $query) {
                    $query->withSelected(request('selected'));
                });

            if (request()->input('perPage') === "-1") {
                $items = $query->get();
                return new LengthAwarePaginator($items, $items->count(), ($items->count() > 0 ? $items->count() : 1), 1) ;
            } else {
                return $query->paginate(request()->input('perPage', 10), ['*']);
            }
        };
    }
}
