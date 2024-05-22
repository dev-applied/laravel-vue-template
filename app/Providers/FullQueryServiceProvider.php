<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

class FullQueryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        Builder::macro('getFullQuery', function(): string {
            /** @var Builder $this  */
            $sql = $this->toSql();
            $bindings = $this->getBindings();
            $sql_with_bindings = preg_replace_callback('/\?/', function ($match) use ($sql, &$bindings) {
                return json_encode(array_shift($bindings));
            }, $sql);

            return str_replace('"', '\'', $sql_with_bindings);
        });
    }
}
