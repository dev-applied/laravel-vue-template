<?php

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Report drift between this project's vendored modules and the firm modules
 * repo. NOTIFY-ONLY: applying an update is deliberate, billable work (retainer
 * or upgrade engagement) via a three-way merge — see docs/modules.md.
 */
class ModuleOutdatedCommand extends Command
{
    protected $signature = 'module:outdated
        {--from= : Local path to a modules-repo checkout (skips the GitHub API)}
        {--branch= : Override the configured source branch}';

    protected $description = 'Compare vendored module versions against the firm modules repo (notify-only)';

    public function handle(): int
    {
        $manifests = collect(File::glob(base_path('modules/*/module.json')));

        if ($manifests->isEmpty()) {
            $this->components->info('No modules installed (modules/ has no module.json manifests).');

            return self::SUCCESS;
        }

        $rows = $manifests->map(function (string $path) {
            $module   = basename(dirname($path));
            $local    = (array) json_decode(File::get($path), true);
            $upstream = $this->upstreamVersion($module);

            $status = match (true) {
                $upstream === null                        => 'not in modules repo (project-local module)',
                $upstream === ($local['version'] ?? null) => 'current',
                default                                   => 'UPDATE AVAILABLE — merge flow only (docs/modules.md), never a raw overwrite',
            };

            return [$module, $local['version'] ?? '?', $upstream ?? '—', $status];
        });

        $this->table(['Module', 'Local', 'Upstream', 'Status'], $rows->all());

        return self::SUCCESS;
    }

    private function upstreamVersion(string $module): ?string
    {
        if ($from = $this->option('from')) {
            $path = (str_starts_with($from, '/') ? $from : base_path($from))."/modules/{$module}/module.json";

            return File::exists($path) ? (json_decode(File::get($path), true)['version'] ?? null) : null;
        }

        $token = env('MODULES_GITHUB_TOKEN') ?: env('GITHUB_TOKEN');

        if (! $token) {
            $this->components->error('No GitHub token. Set MODULES_GITHUB_TOKEN in .env, or pass --from=<local checkout>.');

            exit(self::FAILURE);
        }

        $repo   = config('modules.source.repo');
        $branch = $this->option('branch') ?: config('modules.source.branch');

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github.raw'])
            ->get("https://api.github.com/repos/{$repo}/contents/modules/{$module}/module.json", ['ref' => $branch]);

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json('version');
    }
}
