<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\File;

/**
 * Apply resolved option selections to a copied-in module.
 *
 * Filesystem effects happen here (delete `drop` globs, write `env` keys). The
 * composer + artisan effects are RETURNED as a plan so the calling command runs
 * them — that keeps Process/composer out of this class's unit tests.
 */
class ModuleOptionApplier
{
    /**
     * @param  array<string, mixed>  $schema  module.json "options"
     * @param  array<string, string|array<int, string>|bool>  $resolved  selections from the resolver
     * @return array{require: array<int,string>, require_dev: array<int,string>, npm: array<int,string>, npm_dev: array<int,string>, run: array<int,string>}
     */
    public function apply(string $moduleDir, array $schema, array $resolved, string $envPath, array $previous = []): array
    {
        $plan = ['require' => [], 'require_dev' => [], 'npm' => [], 'npm_dev' => [], 'run' => []];

        // Before writing the new choice's keys: retire the ones only the
        // OUTGOING choice declared. Switching SmsMessaging from twilio back
        // to the log driver otherwise leaves TWILIO_* sitting in .env —
        // harmless there, and not harmless for an auth or billing variant
        // where a stale key still RESOLVES and something still reads it.
        $this->retireEnv($envPath, $this->envKeysFor($schema, $previous), $this->envKeysFor($schema, $resolved));

        foreach ($schema as $key => $def) {
            if (! array_key_exists($key, $resolved)) {
                continue;
            }

            foreach ($this->selectedChoices((array) $def, $resolved[$key]) as $choice) {
                $effects = (array) (($def['choices'][$choice] ?? []));

                $this->drop($moduleDir, (array) ($effects['drop'] ?? []));
                $this->writeEnv($envPath, (array) ($effects['env'] ?? []));

                $plan['require']     = [...$plan['require'], ...array_values((array) ($effects['require'] ?? []))];
                $plan['require_dev'] = [...$plan['require_dev'], ...array_values((array) ($effects['require_dev'] ?? []))];
                $plan['npm']         = [...$plan['npm'], ...array_values((array) ($effects['npm'] ?? []))];
                $plan['npm_dev']     = [...$plan['npm_dev'], ...array_values((array) ($effects['npm_dev'] ?? []))];
                $plan['run']         = [...$plan['run'], ...array_values((array) ($effects['run'] ?? []))];
            }
        }

        $plan['require']     = array_values(array_unique($plan['require']));
        $plan['require_dev'] = array_values(array_unique($plan['require_dev']));
        $plan['npm']         = array_values(array_unique($plan['npm']));
        $plan['npm_dev']     = array_values(array_unique($plan['npm_dev']));
        $plan['run']         = array_values(array_unique($plan['run']));

        return $plan;
    }

    /**
     * Resolve drop globs to absolute paths (files or directories) under the
     * module dir. Supports "Dir/**" (whole tree), "Dir/*.php" (one level), and
     * literal paths — the same forms drop() honours.
     *
     * @param  array<int, string>  $globs
     * @return array<int, string>
     */
    public function matchGlobs(string $moduleDir, array $globs): array
    {
        $base    = mb_rtrim($moduleDir, '/');
        $matches = [];

        foreach ($globs as $glob) {
            $glob = mb_ltrim($glob, '/');

            if (str_contains($glob, '**')) {
                // "Dir/**" — the directory and everything under it.
                $matches[] = $base.'/'.mb_rtrim(mb_substr($glob, 0, mb_strpos($glob, '**')), '/');
            } elseif (str_contains($glob, '*')) {
                $matches = [...$matches, ...File::glob($base.'/'.$glob)];
            } else {
                $matches[] = $base.'/'.$glob;
            }
        }

        return array_values(array_unique(array_filter($matches, fn (string $p) => File::exists($p))));
    }

    /**
     * The set of RELATIVE file paths a resolved selection drops, expanding any
     * matched directories down to their files. Used by module:configure to diff
     * what a re-selected variant needs added back vs. removed.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string|array<int, string>|bool>  $resolved
     * @return array<int, string>
     */
    public function droppedFiles(string $moduleDir, array $schema, array $resolved): array
    {
        $base  = mb_rtrim($moduleDir, '/');
        $files = [];

        foreach ($schema as $key => $def) {
            if (! array_key_exists($key, $resolved)) {
                continue;
            }

            foreach ($this->selectedChoices((array) $def, $resolved[$key]) as $choice) {
                $globs = (array) ($def['choices'][$choice]['drop'] ?? []);

                foreach ($this->matchGlobs($moduleDir, $globs) as $abs) {
                    if (File::isDirectory($abs)) {
                        foreach (File::allFiles($abs) as $f) {
                            $files[] = mb_ltrim(str_replace($base, '', $f->getPathname()), '/');
                        }
                    } else {
                        $files[] = mb_ltrim(str_replace($base, '', $abs), '/');
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Normalize a selection to the list of choice keys whose effects apply.
     * confirm → "true"/"false"; select → [value]; multiselect → values.
     *
     * @param  array<string, mixed>  $def
     * @param  string|array<int, string>|bool  $value
     * @return array<int, string>
     */
    private function selectedChoices(array $def, string|array|bool $value): array
    {
        $type = $def['type'] ?? 'select';

        return match ($type) {
            'confirm'     => [$value ? 'true' : 'false'],
            'multiselect' => array_values((array) $value),
            default       => [(string) $value],
        };
    }

    /**
     * @param  array<int, string>  $globs
     */
    private function drop(string $moduleDir, array $globs): void
    {
        $parents = [];

        foreach ($this->matchGlobs($moduleDir, $globs) as $match) {
            $parents[] = dirname($match);
            $this->deletePath($match);
        }

        $this->pruneEmptyDirectories($moduleDir, $parents);
    }

    /**
     * Remove directories the drop just emptied.
     *
     * An installed module carrying an empty `Mail/` reads as a broken install —
     * the option removed the mailable, not the concept of mail. Walks upward so
     * dropping the only file in `resources/views/mail/` takes the now-empty
     * `mail/` with it, and stops at the module root, which always stays.
     *
     * @param  list<string>  $directories
     */
    private function pruneEmptyDirectories(string $moduleDir, array $directories): void
    {
        $root = mb_rtrim((string) realpath($moduleDir), DIRECTORY_SEPARATOR);

        foreach (array_unique($directories) as $directory) {
            $current = (string) realpath($directory);

            while ($current !== '' && $current !== $root && str_starts_with($current, $root.DIRECTORY_SEPARATOR)) {
                if (! File::isDirectory($current) || File::files($current) !== [] || File::directories($current) !== []) {
                    break;
                }

                File::deleteDirectory($current);
                $current = dirname($current);
            }
        }
    }

    private function deletePath(string $path): void
    {
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        } elseif (File::exists($path)) {
            File::delete($path);
        }
    }

    /**
     * Env keys declared by the choices a given option selection resolves to.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $selection
     * @return list<string>
     */
    private function envKeysFor(array $schema, array $selection): array
    {
        $keys = [];

        foreach ($schema as $key => $def) {
            if (! array_key_exists($key, $selection)) {
                continue;
            }

            foreach ($this->selectedChoices((array) $def, $selection[$key]) as $choice) {
                $env = (array) (($def['choices'][$choice]['env'] ?? []));

                foreach (array_keys($env) as $envKey) {
                    $keys[] = (string) $envKey;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Comment out env keys the outgoing choice declared and the incoming one
     * does not.
     *
     * Commented, NOT deleted, and this is the whole design decision. The value
     * may be a real credential the developer pasted in, and a tool that silently
     * destroys one is worse than the stale-key problem it is solving. Commenting
     * stops it resolving — which is the actual hazard — while leaving it
     * recoverable by eye.
     *
     * A key already commented is left alone, so re-running is a no-op rather
     * than stacking `# # KEY=`.
     *
     * @param  list<string>  $outgoing
     * @param  list<string>  $incoming
     */
    private function retireEnv(string $envPath, array $outgoing, array $incoming): void
    {
        $orphans = array_diff($outgoing, $incoming);

        if ($orphans === [] || ! File::exists($envPath)) {
            return;
        }

        $contents = File::get($envPath);

        foreach ($orphans as $key) {
            $contents = preg_replace(
                '/^('.preg_quote($key, '/').'=.*)$/m',
                '# $1  # retired: no longer declared by the selected option',
                $contents,
            );
        }

        File::put($envPath, $contents);
    }

    /**
     * Add or replace each KEY=value line in the env file (append if missing).
     *
     * @param  array<string, scalar>  $env
     */
    private function writeEnv(string $envPath, array $env): void
    {
        if ($env === [] || ! File::exists($envPath)) {
            return;
        }

        $contents = File::get($envPath);

        foreach ($env as $key => $value) {
            $line     = $key.'='.$this->envValue((string) $value);
            $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents, 1, $count);

            if ($count === 0) {
                $contents = mb_rtrim($contents, "\n").PHP_EOL.$line.PHP_EOL;
            }
        }

        File::put($envPath, $contents);
    }

    private function envValue(string $value): string
    {
        return preg_match('/\s/', $value) ? '"'.$value.'"' : $value;
    }
}
