<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin Model
 */
trait WithSelected {

    public function scopeWithSelected(Builder $query, $selected, $key): Builder
    {
        if (!is_array($selected)) {
            $selected = [$selected];
        }
        $selected = collect($selected)->filter();

        if($selected->count() === 0) {
            return $query;
        }

        $table = $this->getTable();

        $orderBy = $query->getQuery()->orders ?: [];
        $query->getQuery()->orders = [];
        $query->getQuery()->bindings['order'] = [];

        $originalQuery = $query->clone();

        $query->getQuery()->wheres = [];
        $query->getQuery()->bindings['where'] = [];

        if (!$query->getQuery()->columns) {
            $query->select($table . '.*');
        }

        if (!$originalQuery->getQuery()->columns) {
            $originalQuery->select($table . '.*');
        }

        $case = 'CASE';
        foreach ($selected as $select) {
            $case .= " WHEN $table.$key = ? THEN 1";
        }
        $case .= ' ELSE 2 END as sequence';
        $query->selectRaw($case, $selected->toArray());
        $originalQuery->selectRaw($case, $selected->toArray());


        if(in_array(SoftDeletes::class, class_uses_recursive(self::class))) {
            $query->withTrashed();
        }
        $query
            ->whereIn("$table.$key", $selected)
            ->union($originalQuery)
            ->orderBy('sequence')
            ->tap(function ($query) use ($orderBy) {
                foreach ($orderBy as $order) {
                    if(isset($order['type']) && $order['type'] === 'Raw') {
                        $query->orderByRaw($order['sql']);
                        continue;
                    }
                    $query->orderBy(last(explode('.',$order['column'])), $order['direction']);
                }
            });

        return $query;
    }
}
