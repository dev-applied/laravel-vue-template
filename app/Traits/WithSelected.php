<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Model
 */
trait WithSelected {

    public function scopeWithSelected(Builder $query, $selected): Builder
    {
        if (!is_array($selected)) {
            $selected = [$selected];
        }
        $selected = collect($selected)->filter();

        if($selected->count() === 0) {
            return $query;
        }

        $originalQuery = $query->clone();

        $query->getQuery()->wheres = [];
        $query->getQuery()->bindings['where'] = [];


        if(in_array(SoftDeletes::class, class_uses_recursive(self::class))) {
            $query->withTrashed();
        }
        $query
            ->whereIn($this->getTable(). '.id', $selected)
            ->union($originalQuery);

        return $query;
    }
}
