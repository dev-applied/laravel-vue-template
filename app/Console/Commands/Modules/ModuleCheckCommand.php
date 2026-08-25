<?php

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Verify that every installed module's declared dependencies are actually
 * present in this project's composer.json / package.json.
 *
 * `module:add` runs `composer require` for you, which is why this gap is easy
 * to miss: the packages land in the working copy and everything passes locally.
 * If composer.json and composer.lock are then not COMMITTED, the module is
 * committed without the thing it needs to run, and a fresh clone fatals at the
 * first line that touches it.
 *
 * That is exactly how three CI legs went red on 2026-08-25: the template
 * bundles all 22 modules, so every leg runs every bundled module's tests, and
 * `modules/Files` was committed while `imagine/imagine` was not — producing
 * `Class "Imagine\Imagick\Imagine" not found` in legs that had nothing to do
 * with Files.
 */
class ModuleCheckCommand extends Command
{
    protected $signature = 'module:check
        {--defaults : Also fail if an installed module sits on a non-default option variant}
        {--allow=* : Drift to permit under --defaults, as Module:axis=choice}';

    protected $description = "Check that installed modules' declared dependencies are present in composer.json / package.json";

    public function handle(): int
    {
        $composer = $this->packageNames('composer.json', ['require', 'require-dev']);
        $npm      = $this->packageNames('package.json', ['dependencies', 'devDependencies']);

        $missing = [];
        $drift   = [];

        foreach (File::glob(base_path('modules/*/module.json')) as $path) {
            $module = basename(dirname($path));

            // A module can be installed or removed while this is running —
            // `module:add` writes the manifest last, and a deploy may run both
            // at once. Reading a path the glob saw a moment ago is therefore
            // not guaranteed, and throwing there reports a dependency problem
            // that does not exist. Skip what vanished; the next run sees it.
            $contents = File::exists($path) ? File::get($path) : null;

            if ($contents === null) {
                continue;
            }

            $manifest = json_decode($contents, true) ?: [];

            foreach ($this->declared($manifest, 'composer_requires') as $package) {
                if (! in_array($this->nameOf($package), $composer, true)) {
                    $missing[] = [$module, 'composer.json', $package];
                }
            }

            foreach ($this->declared($manifest, 'npm_requires') as $package) {
                if (! in_array($this->nameOf($package), $npm, true)) {
                    $missing[] = [$module, 'package.json', $package];
                }
            }

            if ($this->option('defaults')) {
                $drift = array_merge($drift, $this->drift($module, $manifest));
            }
        }

        if ($drift !== []) {
            $this->components->error('Installed modules sit on a non-default option variant:');
            $this->table(['Module', 'Option', 'Installed', 'Default'], $drift);
            $this->line('  In the TEMPLATE this is drift, not configuration — a bundled module is meant to');
            $this->line('  ship the variant its own manifest calls default. It has shipped a QA-only');
            $this->line('  entitlement switcher this way, left behind by a verification run.');
            $this->line('  Reinstall at the default, or pass <options=bold>--allow=Module:axis=choice</> if it is deliberate.');

            return self::FAILURE;
        }

        if ($missing === []) {
            $this->components->info('Every installed module has its declared dependencies.');

            return self::SUCCESS;
        }

        $this->components->error('Installed modules are missing declared dependencies:');
        $this->table(['Module', 'Manifest', 'Missing package'], $missing);
        $this->line('  These were probably installed by <options=bold>module:add</> and never committed.');
        $this->line('  Run the install again, then commit composer.json / composer.lock / package.json.');

        return self::FAILURE;
    }

    /**
     * Installed choices that differ from the manifest's own stated default.
     *
     * Only meaningful with --defaults, and only in the template: a real project
     * picks variants on purpose, so drift there is the normal case. The template
     * is the one tree where "whatever the manifest calls default" is the whole
     * intent, and where a leftover from a verification run is otherwise
     * invisible — the switcher incident was found by reading a git diff.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function drift(string $module, array $manifest): array
    {
        $allowed = (array) $this->option('allow');
        $found   = [];

        foreach ((array) ($manifest['installed_options'] ?? []) as $option => $choice) {
            $default = $manifest['options'][$option]['default'] ?? null;

            if ($default === null || (string) $choice === (string) $default) {
                continue;
            }

            if (in_array("{$module}:{$option}={$choice}", $allowed, true)) {
                continue;
            }

            $found[] = [$module, $option, (string) $choice, (string) $default];
        }

        return $found;
    }

    /**
     * Base requirements plus the ones belonging to the options this install
     * actually chose — a variant that was never selected must not be demanded.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function declared(array $manifest, string $key): array
    {
        $declared = (array) ($manifest[$key] ?? []);

        foreach ((array) ($manifest['installed_options'] ?? []) as $option => $choice) {
            $declared = array_merge(
                $declared,
                (array) ($manifest['options'][$option]['choices'][$choice][$key] ?? [])
            );
        }

        return array_values(array_unique(array_filter($declared)));
    }

    /**
     * @return list<string>
     */
    private function packageNames(string $file, array $sections): array
    {
        $json  = json_decode((string) File::get(base_path($file)), true) ?: [];
        $names = [];

        foreach ($sections as $section) {
            $names = array_merge($names, array_keys((array) ($json[$section] ?? [])));
        }

        return $names;
    }

    /** `vendor/package:^1.2` and `pkg@^1.2` both reduce to the name. */
    private function nameOf(string $package): string
    {
        $package = str_contains($package, ':') ? explode(':', $package)[0] : $package;

        // npm scoped packages start with @, so only split on a LATER @.
        $at = mb_strpos($package, '@', 1);

        return $at === false ? $package : mb_substr($package, 0, $at);
    }
}
