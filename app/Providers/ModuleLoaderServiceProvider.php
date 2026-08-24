<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers every module's ModuleServiceProvider from modules/<Name>/.
 *
 * Modules are copy-in vendored (committed to this repo by `project:init`),
 * never composer-required — see docs/modules.md. A directory under modules/
 * IS the enable switch: drop a module in and it loads; delete it and it is
 * gone. There is no activator, no statuses file, nothing to toggle.
 */
class ModuleLoaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (glob(base_path('modules/*/ModuleServiceProvider.php')) ?: [] as $path) {
            $module = basename(dirname($path));
            $class  = "Modules\\{$module}\\ModuleServiceProvider";

            if (class_exists($class)) {
                $this->app->register($class);
            }
        }
    }
}
