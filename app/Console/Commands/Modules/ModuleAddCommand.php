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
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * Copy-in one or more modules from the firm modules repo. The module becomes
 * part of THIS repo (committed, customizable) — never a composer dependency.
 * See docs/modules.md.
 *
 * A module may declare `options` in its module.json (e.g. Auth: Sanctum vs
 * Sanctum + Passport OAuth). Those are prompted here (or taken from --option)
 * and the answer prunes files, adds composer deps, sets .env keys, and runs
 * post-install commands. The chosen answers are stamped into the installed
 * module.json as `installed_options` so updates replay them and module:configure
 * can change them later.
 */
class ModuleAddCommand extends Command
{
    protected $signature = 'module:add
        {names?* : Module name(s) — omit to pick interactively}
        {--option=* : Preset a module option, key=value (repeatable); skips its prompt}
        {--no-install-deps : Print the composer require line instead of running it}
        {--from= : Local path to a modules-repo checkout (skips the GitHub API)}
        {--branch= : Override the configured source branch}';

    protected $description = 'Copy one or more modules from the firm modules repo into this project';

    private ModuleSource $source;

    public function handle(): int
    {
        $this->source = new ModuleSource($this->option('from'), $this->option('branch'));

        try {
            $available = $this->source->available();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($available === []) {
            $this->components->error('No modules found at the source.');

            return self::FAILURE;
        }

        $names = $this->argument('names') ?: $this->pickModules($available);

        $failures = 0;
        $plan     = ['require' => [], 'require_dev' => [], 'run' => []];

        foreach ($names as $name) {
            if (! isset($available[$name])) {
                $this->components->error("Unknown module \"{$name}\" — available: ".implode(', ', array_keys($available)));
                $failures++;

                continue;
            }

            if (File::exists(base_path("modules/{$name}"))) {
                $this->components->error("modules/{$name} already exists. Change its options with `module:configure {$name}`, or update via the merge flow — see docs/modules.md.");
                $failures++;

                continue;
            }

            spin(fn () => $this->copyModule($name), "Adding {$name}");

            $modulePlan = $this->applyOptions($name);

            foreach (['require', 'require_dev', 'run'] as $bucket) {
                $plan[$bucket] = [...$plan[$bucket], ...$modulePlan[$bucket]];
            }

            $this->components->info("Module {$name} added (source: {$this->source->label()})");
        }

        $this->installDependencies($plan);

        Process::path(base_path())->run('composer dump-autoload --quiet');
        $this->components->info('composer dump-autoload complete');

        $this->runHooks($plan['run']);

        note(<<<'NEXT'
            Finish up:
              php artisan migrate                       # module migrations
              php artisan route:clear                   # BEFORE any build — Wayfinder reads the cached route table
              composer typescript
            Then restart the vite dev server so the route/page globs pick the module(s) up.
            NEXT);

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, array<string, mixed>>  $available
     * @return array<int, string>
     */
    private function pickModules(array $available): array
    {
        $options = collect($available)
            ->mapWithKeys(fn (array $m, string $name) => [
                $name => mb_trim(sprintf('%s (v%s) — %s', $name, $m['version'] ?? '?', str($m['description'] ?? '')->limit(70))),
            ])
            ->all();

        return multiselect(
            label: 'Which modules should be added?',
            options: $options,
            required: true,
            hint: 'Space selects, enter confirms. Modules are copied into modules/ and become this project\'s code.',
        );
    }

    private function copyModule(string $name): void
    {
        $dest   = base_path("modules/{$name}");
        $commit = $this->source->fetchInto($name, $dest);

        ModuleManifest::forModuleDir($dest)->persist([
            'installed_from_commit' => $commit,
            'installed_at'          => now()->toDateString(),
        ]);
    }

    /**
     * Resolve + apply this module's options, persist the choices, and return the
     * composer/run plan (base deps + the chosen options' deps).
     *
     * @return array{require: array<int,string>, require_dev: array<int,string>, run: array<int,string>}
     */
    private function applyOptions(string $name): array
    {
        $dir      = base_path("modules/{$name}");
        $manifest = ModuleManifest::forModuleDir($dir);

        $plan = [
            'require'     => $manifest->composerRequires(),
            'require_dev' => $manifest->composerRequiresDev(),
            'run'         => [],
        ];

        $schema = $manifest->optionsSchema();

        if ($schema === []) {
            return $plan;
        }

        $resolved = (new ModuleOptionResolver)->resolve(
            $schema,
            (array) $this->option('option'),
            $this->option('no-interaction') ? null : $this->prompter(),
        );

        $applied = (new ModuleOptionApplier)->apply($dir, $schema, $resolved, base_path('.env'));

        foreach (['require', 'require_dev', 'run'] as $bucket) {
            $plan[$bucket] = [...$plan[$bucket], ...$applied[$bucket]];
        }

        $manifest->persist(['installed_options' => $resolved]);

        $summary = collect($resolved)->map(fn ($v, $k) => $k.'='.(is_array($v) ? implode('+', $v) : $v))->implode(', ');
        $this->components->info("  options: {$summary}");

        return $plan;
    }

    private function prompter(): callable
    {
        return function (string $key, array $def) {
            $type  = $def['type'] ?? 'select';
            $label = $def['prompt'] ?? $key;

            if ($type === 'confirm') {
                return confirm($label, default: (bool) ($def['default'] ?? false));
            }

            $choices = collect($def['choices'] ?? [])
                ->mapWithKeys(fn (array $c, string $k) => [$k => $c['label'] ?? $k])
                ->all();

            if ($type === 'multiselect') {
                return multiselect($label, $choices, default: (array) ($def['default'] ?? []));
            }

            return select($label, $choices, default: $def['default'] ?? array_key_first($choices));
        };
    }

    /**
     * @param  array{require: array<int,string>, require_dev: array<int,string>, run: array<int,string>}  $plan
     */
    private function installDependencies(array $plan): void
    {
        $require    = array_values(array_unique($plan['require']));
        $requireDev = array_values(array_unique($plan['require_dev']));

        if ($require === [] && $requireDev === []) {
            return;
        }

        if ($this->option('no-install-deps')) {
            $this->components->warn('Skipped dependency install (--no-install-deps). Run:');
            $require !== [] && $this->line('  composer require '.implode(' ', $require).' -W');
            $requireDev !== [] && $this->line('  composer require --dev '.implode(' ', $requireDev).' -W');

            return;
        }

        if ($require !== []) {
            spin(
                fn () => Process::path(base_path())->timeout(600)->run('composer require '.implode(' ', $require).' -W --no-interaction')->throw(),
                'composer require '.implode(' ', $require),
            );
        }

        if ($requireDev !== []) {
            spin(
                fn () => Process::path(base_path())->timeout(600)->run('composer require --dev '.implode(' ', $requireDev).' -W --no-interaction')->throw(),
                'composer require --dev '.implode(' ', $requireDev),
            );
        }

        $this->components->info('Module composer dependencies installed');
    }

    /**
     * Run each post-install hook as a FRESH `php artisan` subprocess — the
     * module's provider/commands booted before the module existed, so a new
     * process is what discovers them and reads the just-written .env keys.
     *
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
