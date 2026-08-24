<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modules source repository
    |--------------------------------------------------------------------------
    | Where `project:init` copies modules FROM and `modules:check-upstream`
    | compares vendored stamps AGAINST. One private repo holds every module
    | (modules/<Name>/ at its root); modules travel by copy, never by
    | composer require. See docs/modules.md.
    */

    'source' => [
        'repo'   => env('MODULES_SOURCE_REPO', 'dev-applied/laravel-vue-modules'),
        'branch' => env('MODULES_SOURCE_BRANCH', 'main'),
    ],

];
