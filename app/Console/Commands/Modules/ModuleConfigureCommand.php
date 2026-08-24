<?php

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleOptionApplier;
use App\Support\Modules\ModuleOptionResolver;
use App\Support\Modules\ModuleSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * Change an installed module's options after the fact — switch a vendor, turn a
 * layer on, etc. Re-prompts pre-filled with the current answers, then makes the
 * file set match the new selection: files a newly-selected choice needs but that
 * were previously dropped are fetched back from a pristine source copy; files
 * the new selection drops are removed. Existing files are NEVER overwritten, so
 * client customizations survive. Composer deps and .env are swapped to match,
 * and the new choices' post-install hooks run.
 *
 * Needs source access (same as module:add): --from=<checkout> or the GitHub API.
 */
class ModuleConfigureCommand extends Command
{
    protected $signature = 'module:configure
        {name : The installed module to reconfigure}
        {--option=* : Preset an option, key=value (repeatable); skips its prompt}
        {--no-install-deps : Print composer changes instead of running them}
        {--from= : Local path to a modules-repo checkout (skips the GitHub API)}
        {--branch= : Override the configured source branch}';

    protected $description = 'Change an installed module\'s options (add/remove files, deps, env to match)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $dir  = base_path("modules/{$name}");

        if (! File::isDirectory($dir)) {
            $this->components->error("modules/{$name} is not installed. Use `module:add {$name}`.");

            return self::FAILURE;
        }

        $manifest = ModuleManifest::forModuleDir($dir);
        $schema   = $manifest->optionsSchema();

        if ($schema === []) {
            $this->components->info("Module {$name} declares no options — nothing to configure.");

            return self::SUCCESS;
        }

        $old = $manifest->installedOptions();
        $new = $this->resolveNew($schema, $old);

        if ($new === $old) {
            $this->components->info('No option changes.');

            return self::SUCCESS;
        }

        $this->components->info('  '.$this->summary($old).'  →  '.$this->summary($new));

        // Pristine copy to source add-back files a newly-selected choice needs.
        $source   = new ModuleSource($this->option('from'), $this->option('branch'));
        $pristine = sys_get_temp_dir()."/module-configure-{$name}-".uniqid();

        try {
            spin(fn () => $source->fetchInto($name, $pristine), "Fetching pristine {$name}");
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $applier = new ModuleOptionApplier;

        $this->addBackFiles($pristine, $dir, $schema, $new, $applier);

        // apply() on the installed dir drops files the new selection no longer
        // needs and writes the new env; it returns the require/run plan.
        $plan = $applier->apply($dir, $schema, $new, base_path('.env'));

        $this->swapDependencies($schema, $old, $new, $plan['require']);

        File::deleteDirectory($pristine);

        Process::path(base_path())->run('composer dump-autoload --quiet');

        $manifest->persist(['installed_options' => $new]);

        $this->runHooks($plan['run']);

        $this->components->info("Module {$name} reconfigured. Run `php artisan route:clear && composer typescript`, then restart vite.");

        return self::SUCCESS;
    }

    /**
     * Resolve the new selection: default falls back to the CURRENT answer, so an
     * option left un-flagged and un-prompted keeps its value.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string|array<int, string>>  $old
     * @return array<string, string|array<int, string>|bool>
     */
    private function resolveNew(array $schema, array $old): array
    {
        // Override each option's default with the current answer.
        $effective = $schema;

        foreach ($effective as $key => $def) {
            if (array_key_exists($key, $old)) {
                $effective[$key]['default'] = $old[$key];
            }
        }

        return (new ModuleOptionResolver)->resolve(
            $effective,
            (array) $this->option('option'),
            $this->option('no-interaction') ? null : $this->prompter($old),
        );
    }

    /**
     * Copy in every file the new selection needs that the installed copy is
     * missing (previously dropped). Never overwrites an existing file.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string|array<int, string>|bool>  $new
     */
    private function addBackFiles(string $pristine, string $installed, array $schema, array $new, ModuleOptionApplier $applier): void
    {
        $dropped = $applier->droppedFiles($pristine, $schema, $new);

        foreach (File::allFiles($pristine) as $file) {
            $rel = mb_ltrim(str_replace(mb_rtrim($pristine, '/'), '', $file->getPathname()), '/');

            if (in_array($rel, $dropped, true)) {
                continue;                       // the new selection drops this
            }

            $target = mb_rtrim($installed, '/').'/'.$rel;

            if (File::exists($target)) {
                continue;                       // keep the (possibly customized) installed file
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getPathname(), $target);
        }
    }

    /**
     * composer require the deps the new selection needs; composer remove the
     * ones only the old selection needed (never base deps).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string|array<int, string>>  $old
     * @param  array<string, string|array<int, string>|bool>  $new
     * @param  array<int, string>  $newPlanRequire  base + new option requires
     */
    private function swapDependencies(array $schema, array $old, array $new, array $newPlanRequire): void
    {
        $oldPkgs = $this->packageNames($this->optionRequires($schema, $old));
        $newPkgs = $this->packageNames($this->optionRequires($schema, $new));

        $toAdd    = array_values(array_filter($newPlanRequire, fn (string $r) => ! in_array($this->packageName($r), $oldPkgs, true)));
        $toRemove = array_values(array_diff($oldPkgs, $newPkgs));

        if ($this->option('no-install-deps')) {
            $toAdd !== [] && $this->line('  composer require '.implode(' ', $toAdd).' -W');
            $toRemove !== [] && $this->line('  composer remove '.implode(' ', $toRemove));

            return;
        }

        if ($toAdd !== []) {
            spin(fn () => Process::path(base_path())->timeout(600)->run('composer require '.implode(' ', $toAdd).' -W --no-interaction')->throw(), 'composer require '.implode(' ', $toAdd));
        }

        if ($toRemove !== []) {
            spin(fn () => Process::path(base_path())->timeout(600)->run('composer remove '.implode(' ', $toRemove).' --no-interaction')->throw(), 'composer remove '.implode(' ', $toRemove));
        }
    }

    /**
     * Union of the `require` entries across a selection's chosen choices.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string|array<int, string>|bool>  $resolved
     * @return array<int, string>
     */
    private function optionRequires(array $schema, array $resolved): array
    {
        $out = [];

        foreach ($schema as $key => $def) {
            if (! array_key_exists($key, $resolved)) {
                continue;
            }

            $choices = $this->selectedChoiceKeys((array) $def, $resolved[$key]);

            foreach ($choices as $choice) {
                $out = [...$out, ...array_values((array) ($def['choices'][$choice]['require'] ?? []))];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  string|array<int, string>|bool  $value
     * @return array<int, string>
     */
    private function selectedChoiceKeys(array $def, string|array|bool $value): array
    {
        return match ($def['type'] ?? 'select') {
            'confirm'     => [$value ? 'true' : 'false'],
            'multiselect' => array_values((array) $value),
            default       => [(string) $value],
        };
    }

    /**
     * @param  array<int, string>  $requires
     * @return array<int, string>
     */
    private function packageNames(array $requires): array
    {
        return array_values(array_unique(array_map($this->packageName(...), $requires)));
    }

    private function packageName(string $require): string
    {
        return explode(':', $require, 2)[0];
    }

    /**
     * @param  array<string, string|array<int, string>>  $old
     */
    private function prompter(array $old): callable
    {
        return function (string $key, array $def) use ($old) {
            $type    = $def['type'] ?? 'select';
            $label   = $def['prompt'] ?? $key;
            $current = $old[$key] ?? ($def['default'] ?? null);

            if ($type === 'confirm') {
                return confirm($label, default: (bool) $current);
            }

            $choices = collect($def['choices'] ?? [])
                ->mapWithKeys(fn (array $c, string $k) => [$k => $c['label'] ?? $k])
                ->all();

            if ($type === 'multiselect') {
                return multiselect($label, $choices, default: (array) $current);
            }

            return select($label, $choices, default: $current ?? array_key_first($choices));
        };
    }

    /**
     * @param  array<string, string|array<int, string>|bool>  $resolved
     */
    private function summary(array $resolved): string
    {
        return collect($resolved)
            ->map(fn ($v, $k) => $k.'='.(is_array($v) ? implode('+', $v) : ($v === true ? 'true' : ($v === false ? 'false' : $v))))
            ->implode(', ');
    }

    /**
     * @param  array<int, string>  $commands
     */
    private function runHooks(array $commands): void
    {
        foreach (array_unique($commands) as $command) {
            $this->components->info("Running: php artisan {$command}");
            $result = Process::path(base_path())->timeout(600)->run('php artisan '.$command);

            if ($result->failed()) {
                $this->components->error("Hook failed: php artisan {$command}");
                $this->line($result->errorOutput() ?: $result->output());
            }
        }
    }
}
