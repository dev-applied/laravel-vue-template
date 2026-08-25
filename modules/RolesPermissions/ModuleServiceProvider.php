<?php

declare(strict_types=1);

namespace Modules\RolesPermissions;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\RolesPermissions\Console\Commands\GrantAdminCommand;
use Modules\RolesPermissions\Models\Permission;
use Modules\RolesPermissions\Models\Role;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Point spatie at this module's models rather than publishing its config.
        // A project that DOES publish config/permission.php overrides this, which
        // is the right precedence — its file is more specific than our default.
        config([
            'permission.models.role'       => Role::class,
            'permission.models.permission' => Permission::class,
        ]);
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // The aliases route definitions expect: ->middleware('permission:items.edit')
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([GrantAdminCommand::class]);
        }
    }
}
