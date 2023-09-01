<?php

namespace App\Mixins;

use Illuminate\Database\Eloquent\Builder;
use Schema;

/**
 * Class VuetifyPaginateMixin
 * @package App\Mixins
 * @mixin Builder
 */
class VuetifyPaginateMixin
{
    public function vuetifyPaginate(): \Closure
    {
        return function () {
            $this->when(request()->input('sort'), function (Builder $query, string $sort) {
                if (Schema::hasColumn($this->getModel()->getTable(), $sort)) {
                    $this->orderBy($sort, request()->input('direction'));
                }
            });
            return $this->paginate(request()->input('perPage', 10), ['*']);
        };
    }
}
