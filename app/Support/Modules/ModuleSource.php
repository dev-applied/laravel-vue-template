<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The firm modules repo as a source of module code, reachable two ways:
 *  - a local checkout (`--from=<path>`), or
 *  - the GitHub API (config modules.source.repo + MODULES_GITHUB_TOKEN).
 *
 * Shared by module:add (initial copy) and module:configure (re-fetch pristine
 * files when a re-selected option needs a previously-dropped file back).
 */
class ModuleSource
{
    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $branchOverride = null,
    ) {}

    public function isLocal(): bool
    {
        return $this->from !== null && $this->from !== '';
    }

    public function label(): string
    {
        return $this->isLocal()
            ? (string) $this->localPath()
            : config('modules.source.repo').'@'.$this->branch();
    }

    public function branch(): string
    {
        return $this->branchOverride ?: (string) config('modules.source.branch');
    }

    /**
     * @return array<string, array<string, mixed>> name => manifest
     */
    public function available(): array
    {
        if ($this->isLocal()) {
            return collect(File::directories($this->localPath().'/modules'))
                ->mapWithKeys(function (string $dir) {
                    $manifest = "{$dir}/module.json";

                    return [basename($dir) => File::exists($manifest) ? (array) json_decode(File::get($manifest), true) : []];
                })
                ->all();
        }

        $listing = $this->github('contents/modules')->collect();

        return $listing
            ->where('type', 'dir')
            ->mapWithKeys(function (array $entry) {
                $manifest = $this->github("contents/modules/{$entry['name']}/module.json")->json();
                $decoded  = isset($manifest['content']) ? (array) json_decode(base64_decode($manifest['content']), true) : [];

                return [$entry['name'] => $decoded];
            })
            ->all();
    }

    /**
     * Copy the module's files into $dest. Returns the source commit SHA (for the
     * installed_from_commit stamp), or 'unknown'.
     */
    public function fetchInto(string $name, string $dest): string
    {
        if ($this->isLocal()) {
            File::copyDirectory($this->localPath()."/modules/{$name}", $dest);

            return mb_trim(Process::path($this->localPath())->run('git rev-parse HEAD')->output()) ?: 'unknown';
        }

        $this->downloadFromGithub($name, $dest);

        return $this->github('commits/'.$this->branch())->json('sha') ?? 'unknown';
    }

    private function downloadFromGithub(string $name, string $dest): void
    {
        $repo       = config('modules.source.repo');
        $tarball    = sys_get_temp_dir()."/module-{$name}-".uniqid().'.tar.gz';
        $extractDir = sys_get_temp_dir()."/module-{$name}-".uniqid();

        Http::withToken($this->token())
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->timeout(120)
            ->sink($tarball)
            ->get("https://api.github.com/repos/{$repo}/tarball/{$this->branch()}")
            ->throw();

        File::makeDirectory($extractDir, recursive: true);

        // GitHub tarballs nest everything under a "<owner>-<repo>-<sha>/" top
        // dir. Strip it, and extract only this module's subtree. GNU tar needs
        // --wildcards to glob member names (BSD tar globs by default), and the
        // trailing /* is what pulls the files under the dir (with --wildcards,
        // * spans slashes).
        Process::run([
            'tar', '-xzf', $tarball, '-C', $extractDir,
            '--strip-components=1', '--wildcards', "*/modules/{$name}/*",
        ])->throw();

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
            throw new RuntimeException(
                'No GitHub token. Set MODULES_GITHUB_TOKEN in .env (a fine-grained PAT with read access to '
                .config('modules.source.repo').'), or pass --from=<local checkout>.'
            );
        }

        return $token;
    }

    private function localPath(): string
    {
        $path = str_starts_with((string) $this->from, '/') ? (string) $this->from : base_path((string) $this->from);

        if (! File::isDirectory("{$path}/modules")) {
            throw new RuntimeException("--from path {$path} has no modules/ directory.");
        }

        return $path;
    }
}
