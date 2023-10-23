<?php

namespace App\Mixins;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            $this->when(request()->input('sort'), function (Builder $query, string $sort) {
                if (Schema::hasColumn($this->getModel()->getTable(), $sort)) {
                    $this->orderBy($sort, request()->input('direction'));
                }
            });
            if (request()->input('perPage') === "-1") {
                $items = $this->get();
                return new LengthAwarePaginator($items, $items->count(), $items->count(), 1) ;
            } else {
                return $this->paginate(request()->input('perPage', 10), ['*']);
            }
        };
    }
}
