<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\File;

/**
 * Vite's pre-bundled dependency cache.
 *
 * Adding or removing a module changes what the `/modules/*` route and page globs
 * resolve to, and the Vuetify plugin's virtual modules are cached alongside the
 * pre-bundled deps. A plain dev-server restart keeps that cache, so the next
 * page load 404s on `virtual:plugin-vuetify:components/*.sass` for effectively
 * every component and the app renders blank — with no error that names the
 * cause. Clearing the directory is the fix, and it costs one re-bundle.
 */
class ViteCache
{
    public static function clear(): bool
    {
        $path = base_path('node_modules/.vite');

        if (! File::isDirectory($path)) {
            return false;
        }

        return File::deleteDirectory($path);
    }
}
