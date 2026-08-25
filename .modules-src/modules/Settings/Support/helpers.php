<?php

declare(strict_types=1);

use Modules\Settings\Support\SettingsManager;

if (! function_exists('setting')) {
    /**
     * Read a setting. Cached, so this is safe to call in a view or a loop.
     *
     *   setting('support.email')
     *   setting('features.beta', false)
     */
    function setting(string $key, mixed $fallback = null): mixed
    {
        return app(SettingsManager::class)->get($key, $fallback);
    }
}
