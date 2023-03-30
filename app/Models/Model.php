<?php

namespace App\Models;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as BaseModel;

/**
 * App\Models\Model
 *
 * @method static Builder|Model newModelQuery()
 * @method static Builder|Model newQuery()
 * @method static Builder|Model query()
 * @mixin Eloquent
 */
class Model extends BaseModel
{

    public static function getQueryFull(Builder|\Illuminate\Database\Query\Builder $builder): string
    {
        $sql = $builder->toSql();
        $bindings = $builder->getBindings();
        $sql_with_bindings = preg_replace_callback('/\?/', function ($match) use ($sql, &$bindings) {
            return json_encode(array_shift($bindings));
        }, $sql);

        return str_replace('"', '\'', $sql_with_bindings);
    }

}