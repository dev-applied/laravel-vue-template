<?php

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

/**
 * Copy-in one or more modules from the firm modules repo. The module becomes
 * part of THIS repo (committed, customizable) — never a composer dependency.
 * See docs/modules.md.
 *
 * Sources, in order of precedence:
 *  --from=<path>   a local checkout of the modules repo (path relative to the
 *                  project root or absolute inside the container)
 *  GitHub API      config modules.source.repo, authenticated with
 *                  MODULES_GITHUB_TOKEN (or GITHUB_TOKEN) from .env — works
 *                  from inside the container, no git/gh needed.
 */
class ModuleAddCommand extends Command
{
    protected $signature = 'module:add
        {names?* : Module name(s) — omit to pick interactively}
        {--from= : Local path to a modules-repo checkout (skips the GitHub API)}
        {--branch= : Override the configured source branch}';

    protected $description = 'Copy one or more modules from the firm modules repo into this project';

    public function handle(): int
    {
        $available = $this->availableModules();

        if ($available === []) {
            $this->components->error('No modules found at the source.');

            return self::FAILURE;
        }

        $names = $this->argument('names');

        if ($names === []) {
            $options = collect($available)
                ->mapWithKeys(fn (array $m, string $name) => [
                    $name => mb_trim(sprintf('%s (v%s) — %s', $name, $m['version'] ?? '?', str($m['description'] ?? '')->limit(70))),
                ])
                ->all();

            $names = multiselect(
                label: 'Which modules should be added?',
                options: $options,
                required: true,
                hint: 'Space selects, enter confirms. Modules are copied into modules/ and become this project\'s code.',
            );
        }

        $failures = 0;

        foreach ($names as $name) {
            if (! isset($available[$name])) {
                $this->components->error("Unknown module \"{$name}\" — available: ".implode(', ', array_keys($available)));
                $failures++;

                continue;
            }

            if (File::exists(base_path("modules/{$name}"))) {
                $this->components->error("modules/{$name} already exists. Updates are a merge, not a copy — see docs/modules.md.");
                $failures++;

                continue;
            }

            spin(fn () => $this->copyModule($name), "Adding {$name}");
            $this->components->info("Module {$name} added (source: {$this->sourceLabel()})");
        }

        Process::path(base_path())->run('composer dump-autoload --quiet');
        $this->components->info('composer dump-autoload complete');

        note(<<<'NEXT'
            Finish up:
              php artisan migrate                       # module migrations
              php artisan route:clear                   # BEFORE any build — Wayfinder reads the cached route table
              composer typescript
            Then restart the vite dev server so the route/page globs pick the module(s) up.
            NEXT);

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, array{version?: string, description?: string}> */
    private function availableModules(): array
    {
        if ($from = $this->localSource()) {
            return collect(File::directories("{$from}/modules"))
                ->mapWithKeys(function (string $dir) {
                    $manifest = "{$dir}/module.json";

                    return [basename($dir) => File::exists($manifest) ? (array) json_decode(File::get($manifest), true) : []];
                })
                ->all();
        }

        return spin(function () {
            $listing = $this->github('contents/modules')->collect();

            return $listing
                ->where('type', 'dir')
                ->mapWithKeys(function (array $entry) {
                    $manifest = $this->github("contents/modules/{$entry['name']}/module.json")->json();
                    $decoded  = isset($manifest['content']) ? (array) json_decode(base64_decode($manifest['content']), true) : [];

                    return [$entry['name'] => $decoded];
                })
                ->all();
        }, 'Fetching module list from '.config('modules.source.repo'));
    }

    private function copyModule(string $name): void
    {
        $dest = base_path("modules/{$name}");

        if ($from = $this->localSource()) {
            File::copyDirectory("{$from}/modules/{$name}", $dest);
            $commit = mb_trim(Process::path($from)->run('git rev-parse HEAD')->output()) ?: 'unknown';
        } else {
            $this->downloadFromGithub($name, $dest);
            $commit = $this->github('commits/'.$this->branch())->json('sha') ?? 'unknown';
        }

        // Stamp the manifest — the three-way-merge base pointer for updates.
        $manifestPath                      = "{$dest}/module.json";
        $manifest                          = File::exists($manifestPath) ? (array) json_decode(File::get($manifestPath), true) : [];
        $manifest['installed_from_commit'] = $commit;
        $manifest['installed_at']          = now()->toDateString();
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    private function downloadFromGithub(string $name, string $dest): void
    {
        $repo       = config('modules.source.repo');
        $tarball    = sys_get_temp_dir()."/module-{$name}-".uniqid().'.tar.gz';
        $extractDir = sys_get_temp_dir()."/module-{$name}-".uniqid();

        $response = Http::withToken($this->token())
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->timeout(120)
            ->sink($tarball)
            ->get("https://api.github.com/repos/{$repo}/tarball/{$this->branch()}");

        $response->throw();

        File::makeDirectory($extractDir, recursive: true);
        Process::run(['tar', '-xzf', $tarball, '-C', $extractDir, '--strip-components=1', "*/modules/{$name}"])->throw();

        File::copyDirectory("{$extractDir}/modules/{$name}", $dest);
        File::delete($tarball);
        File::deleteDirectory($extractDir);
    }

    private function github(string $path): \Illuminate\Http\Client\Response
    {
        $repo = config('modules.source.repo');

        return Http::withToken($this->token())
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("https://api.github.com/repos/{$repo}/{$path}", ['ref' => $this->branch()])
            ->throw();
    }

    private function token(): string
    {
        $token = env('MODULES_GITHUB_TOKEN') ?: env('GITHUB_TOKEN');

        if (! $token) {
            $this->components->error(
                'No GitHub token. Set MODULES_GITHUB_TOKEN in .env (a fine-grained PAT with read access to '
                .config('modules.source.repo').'), or pass --from=<local checkout>.'
            );

            exit(self::FAILURE);
        }

        return $token;
    }

    private function localSource(): ?string
    {
        $from = $this->option('from');

        if (! $from) {
            return null;
        }

        $path = str_starts_with($from, '/') ? $from : base_path($from);

        if (! File::isDirectory("{$path}/modules")) {
            $this->components->error("--from path {$path} has no modules/ directory.");

            exit(self::FAILURE);
        }

        return $path;
    }

    private function branch(): string
    {
        return $this->option('branch') ?: config('modules.source.branch');
    }

    private function sourceLabel(): string
    {
        return $this->localSource() ?? config('modules.source.repo').'@'.$this->branch();
    }
}
