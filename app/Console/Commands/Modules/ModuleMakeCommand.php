<?php

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

/**
 * Scaffold a new module in the firm modules repo, in the shape docs/modules.md
 * describes and every shipped module follows.
 *
 * This writes into a MODULES-REPO checkout, not into this project. Authoring
 * happens there; `module:add` is what brings a module back into a project. The
 * default destination is a sibling `laravel-vue-modules` directory, which is
 * how the repos sit in practice.
 */
class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make
        {name? : Module name in StudlyCase, e.g. Reports}
        {--model= : Primary model name (singular StudlyCase). Defaults to the singular of the module name}
        {--description= : One line for module.json and the README}
        {--dest= : Path to a modules-repo checkout (default: ../laravel-vue-modules)}
        {--force : Overwrite an existing module directory}';

    protected $description = 'Scaffold a new module in the firm modules repo from the canonical shape';

    public function handle(): int
    {
        $name = $this->argument('name') ?: text(
            'Module name (StudlyCase)',
            'E.g. Reports',
            required: true,
        );

        $module = Str::studly($name);

        if ($module !== $name) {
            note("Using \"{$module}\" — module directories are StudlyCase.");
        }

        $model = Str::studly($this->option('model') ?: Str::singular($module));
        $dest  = $this->resolveDestination();

        if ($dest === null) {
            return self::FAILURE;
        }

        $target = "{$dest}/modules/{$module}";

        if (File::exists($target)) {
            if (! $this->option('force')) {
                $this->components->error("{$target} already exists. Pass --force to overwrite.");

                return self::FAILURE;
            }

            if (! confirm("Overwrite {$target}?", default: false)) {
                return self::FAILURE;
            }

            File::deleteDirectory($target);
        }

        $replacements = $this->replacements($module, $model);

        foreach ($this->fileMap($module, $model) as $stub => $relative) {
            $this->write("{$target}/{$relative}", $stub, $replacements, $relative);
        }

        $this->components->info("Module {$module} scaffolded at {$target}");

        note(implode(PHP_EOL, [
            'Next:',
            '  1. Replace the placeholder `name` column in Database/Migrations with the real schema.',
            '  2. Fill in the README — a README that still reads like a stub is worse than none.',
            '  3. From the modules repo: ./bin/lint',
            "  4. Verify against a template checkout: php artisan module:add {$module} --from=<path>",
        ]));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> stub basename => path inside the module
     */
    private function fileMap(string $module, string $model): array
    {
        $plural    = Str::plural($model);
        $migration = now()->format('Y_m_d_His').'_create_'.Str::snake($plural).'_table.php';

        return [
            'module.json'               => 'module.json',
            'ModuleServiceProvider.php' => 'ModuleServiceProvider.php',
            'Model.php'                 => "Models/{$model}.php",
            'Factory.php'               => "Database/Factories/{$model}Factory.php",
            'migration.php'             => "Database/Migrations/{$migration}",
            'Controller.php'            => "Http/Controllers/{$model}Controller.php",
            'StoreRequest.php'          => "Http/Requests/Store{$model}Request.php",
            'UpdateRequest.php'         => "Http/Requests/Update{$model}Request.php",
            'Resource.php'              => "Http/Resources/{$model}Resource.php",
            'routes-api.php'            => 'Routes/api.php',
            'Test.php'                  => "Tests/Feature/{$module}Test.php",
            'routes.ts'                 => 'resources/ts/routes.ts',
            'composable.ts'             => "resources/ts/composables/use{$module}.ts",
            'Page.vue'                  => "resources/ts/pages/{$module}Page.vue",
            'README.md'                 => 'README.md',
        ];
    }

    /** @return array<string, string> */
    private function replacements(string $module, string $model): array
    {
        $plural = Str::plural($model);

        return [
            // Longest keys first is not enough on its own — `{{ Model }}` is a
            // prefix of nothing here, but `{{ Module }}` and `{{ Model }}` are
            // distinct tokens, so plain replacement is safe. Keep it that way:
            // never add a placeholder that is a prefix of another.
            '{{ Module }}'      => $module,
            '{{ Model }}'       => $model,
            '{{ plural }}'      => Str::camel($plural),
            '{{ singular }}'    => Str::camel($model),
            '{{ table }}'       => Str::snake($plural),
            '{{ kebabPlural }}' => Str::kebab($plural),
            '{{ slug }}'        => Str::kebab($module),
            '{{ CONST }}'       => Str::upper(Str::snake($module)),
            '{{ description }}' => $this->option('description')
                ?: "TODO: one line describing what {$module} does and who it is for.",
            '{{ templateVersion }}' => now()->format('Y-m'),
        ];
    }

    private function write(string $path, string $stub, array $replacements, string $relative = ''): void
    {
        $contents = File::get(base_path("stubs/module/{$stub}.stub"));

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace(array_keys($replacements), array_values($replacements), $contents));

        // The module-relative path, not a fixed number of stripped parent
        // segments: those nest to different depths, so the old version
        // printed a different prefix for each file.
        $this->components->twoColumnDetail($relative, '<fg=green>created</>');
    }

    private function resolveDestination(): ?string
    {
        $dest = $this->option('dest') ?: dirname(base_path()).'/laravel-vue-modules';

        if (! File::isDirectory($dest)) {
            $this->components->error("Modules repo not found at {$dest}. Pass --dest=<path>.");

            return null;
        }

        if (! File::isDirectory("{$dest}/modules")) {
            $this->components->error("{$dest} has no modules/ directory — is that a modules-repo checkout?");

            return null;
        }

        return mb_rtrim($dest, '/');
    }
}
