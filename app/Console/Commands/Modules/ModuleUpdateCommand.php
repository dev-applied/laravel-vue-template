<?php

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleOptionApplier;
use App\Support\Modules\ModuleSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

/**
 * Pull upstream changes into an installed module without discarding the
 * client's customizations.
 *
 * The three-way merge docs/modules.md describes:
 *
 *     base    = upstream at the module's `installed_from_commit`
 *     theirs  = upstream at HEAD
 *     ours    = the copy in this project, customizations and all
 *
 * `git merge-file` does the per-file merge (it is the same engine a normal
 * merge uses, and needs no repository). Conflicts land as conflict markers in
 * the working file, exactly where the client actually diverged — resolving them
 * is the billable part of a module update.
 *
 * `installed_options` is replayed against BOTH upstream trees before diffing.
 * The local copy is option-pruned, so comparing it against unpruned upstream
 * trees would read every dropped file as a client deletion and try to add them
 * all back.
 */
class ModuleUpdateCommand extends Command
{
    protected $signature = 'module:update
        {names?* : Module name(s) — omit to update every installed module}
        {--from= : Local path to a modules-repo checkout (skips the GitHub API)}
        {--branch= : Override the configured source branch}
        {--dry-run : Report what would change and touch nothing}';

    protected $description = 'Three-way merge upstream module changes into this project, preserving local edits';

    private ModuleSource $source;

    private ModuleOptionApplier $applier;

    public function handle(): int
    {
        $this->source  = new ModuleSource($this->option('from'), $this->option('branch'));
        $this->applier = new ModuleOptionApplier;

        $names = $this->argument('names') ?: $this->installedModules();

        if ($names === []) {
            $this->components->warn('No installed modules found in modules/.');

            return self::SUCCESS;
        }

        $conflicted = 0;

        foreach ($names as $name) {
            try {
                $conflicted += $this->updateOne($name);
            } catch (Throwable $e) {
                $this->components->error("{$name}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        if ($conflicted > 0) {
            note(implode(PHP_EOL, [
                "{$conflicted} file(s) have conflict markers.",
                'Resolve them, then run the gate before committing:',
                '  git diff --check                 # find leftover markers',
                '  composer ci && npm run build',
            ]));

            // Non-zero so a script or CI step cannot mistake "merged with
            // conflicts" for "merged cleanly".
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function updateOne(string $name): int
    {
        $local = base_path("modules/{$name}");

        if (! File::isDirectory($local)) {
            throw new RuntimeException("not installed (no modules/{$name})");
        }

        $manifest = ModuleManifest::forModuleDir($local);
        $base     = (string) $manifest->get('installed_from_commit', '');

        if ($base === '' || $base === 'unknown') {
            throw new RuntimeException(
                'no installed_from_commit stamp, so there is no merge base. '
                .'Re-run module:add to re-stamp, or merge by hand.'
            );
        }

        $options = $manifest->installedOptions();
        $work    = sys_get_temp_dir().'/module-update-'.$name.'-'.uniqid();
        $baseDir = "{$work}/base";
        $headDir = "{$work}/head";

        File::makeDirectory($work, recursive: true);

        try {
            $headSha = spin(
                fn () => $this->materialize($name, $baseDir, $headDir, $base, $options),
                "Fetching {$name} at the merge base and at HEAD",
            );

            if ($headSha === $base) {
                $this->components->twoColumnDetail($name, '<fg=gray>already up to date</>');

                return 0;
            }

            return $this->merge($name, $local, $baseDir, $headDir, $manifest, $headSha);
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** Fetch both upstream trees and prune each to the installed option set. */
    private function materialize(string $name, string $baseDir, string $headDir, string $base, array $options): string
    {
        $this->source->fetchInto($name, $baseDir, $base);
        $headSha = $this->source->fetchInto($name, $headDir);

        foreach ([$baseDir, $headDir] as $dir) {
            $schema = ModuleManifest::forModuleDir($dir)->optionsSchema();

            if ($schema !== [] && $options !== []) {
                foreach ($this->applier->droppedFiles($dir, $schema, $options) as $path) {
                    File::delete($path);
                }
            }
        }

        return $headSha;
    }

    private function merge(
        string $name,
        string $local,
        string $baseDir,
        string $headDir,
        ModuleManifest $manifest,
        string $headSha
    ): int {
        $paths = collect([$baseDir, $headDir, $local])
            ->flatMap(fn (string $dir) => $this->relativeFiles($dir))
            ->unique()
            // module.json is re-stamped below rather than merged: it carries
            // install-time state (installed_at, installed_from_commit) that
            // upstream has no version of, so a textual merge would conflict on
            // every single update.
            ->reject(fn (string $p) => $p === 'module.json')
            ->sort()
            ->values();

        $added  = $conflicts = $merged = $upstreamDeleted = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($paths as $path) {
            $inBase  = File::exists("{$baseDir}/{$path}");
            $inHead  = File::exists("{$headDir}/{$path}");
            $inLocal = File::exists("{$local}/{$path}");

            match (true) {
                // New upstream file the client never had.
                ! $inBase && $inHead && ! $inLocal => $added += $this->copyIn("{$headDir}/{$path}", "{$local}/{$path}", $path, $dryRun),

                // Upstream removed it. Reported, never deleted automatically —
                // the client may be depending on it, and an update should not
                // silently take code away.
                $inBase && ! $inHead && $inLocal => $upstreamDeleted += $this->flagRemoval($path),

                // The interesting case.
                $inBase && $inHead && $inLocal => [$merged, $conflicts] = $this->mergeFile(
                    "{$local}/{$path}", "{$baseDir}/{$path}", "{$headDir}/{$path}", $path, $dryRun, $merged, $conflicts
                ),

                default => null,
            };
        }

        $summary = sprintf(
            '%d merged, %d new, %d conflicted, %d removed upstream',
            $merged, $added, $conflicts, $upstreamDeleted
        );

        $this->components->twoColumnDetail(
            $name,
            $conflicts > 0 ? "<fg=yellow>{$summary}</>" : "<fg=green>{$summary}</>"
        );

        if (! $dryRun) {
            $manifest->persist([
                'installed_from_commit' => $headSha,
                'installed_at'          => now()->toIso8601String(),
            ]);
        }

        return $conflicts;
    }

    /**
     * @return array{int, int} [merged, conflicts]
     */
    private function mergeFile(
        string $ours,
        string $base,
        string $theirs,
        string $path,
        bool $dryRun,
        int $merged,
        int $conflicts
    ): array {
        // Identical upstream trees mean upstream did not touch this file, so
        // there is nothing to merge and the client's copy stands.
        if (File::get($base) === File::get($theirs)) {
            return [$merged, $conflicts];
        }

        if ($dryRun) {
            $clean = File::get($ours) === File::get($base);
            $this->line(sprintf('    %s %s', $clean ? '<fg=green>would update</>' : '<fg=yellow>would merge</>', $path));

            return [$merged + 1, $conflicts];
        }

        $target = tempnam(sys_get_temp_dir(), 'merge');
        File::put($target, File::get($ours));

        // -L labels are what the client sees in the conflict markers, so they
        // name the two sides in the vocabulary of a module update rather than
        // as three temp paths.
        $result = Process::run([
            'git', 'merge-file',
            '-L', 'yours (this project)', '-L', 'module base', '-L', 'upstream',
            $target, $base, $theirs,
        ]);

        File::put($ours, File::get($target));
        File::delete($target);

        // git merge-file exits with the number of conflicts, and negative on
        // error — so "not zero" is not the same as "conflicted".
        if ($result->exitCode() < 0) {
            throw new RuntimeException("merge failed for {$path}: ".mb_trim($result->errorOutput()));
        }

        if ($result->exitCode() > 0) {
            $this->line("    <fg=yellow>CONFLICT</> {$path}");

            return [$merged, $conflicts + 1];
        }

        return [$merged + 1, $conflicts];
    }

    private function copyIn(string $from, string $to, string $path, bool $dryRun): int
    {
        if ($dryRun) {
            $this->line("    <fg=green>would add</> {$path}");

            return 1;
        }

        File::ensureDirectoryExists(dirname($to));
        File::copy($from, $to);

        return 1;
    }

    private function flagRemoval(string $path): int
    {
        $this->line("    <fg=gray>removed upstream, kept locally</> {$path}");

        return 1;
    }

    /** @return array<int, string> paths relative to $dir */
    private function relativeFiles(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::allFiles($dir))
            ->map(fn ($f) => $f->getRelativePathname())
            ->all();
    }

    /** @return array<int, string> */
    private function installedModules(): array
    {
        if (! File::isDirectory(base_path('modules'))) {
            return [];
        }

        return collect(File::directories(base_path('modules')))
            ->map(fn (string $d) => basename($d))
            ->filter(fn (string $n) => File::exists(base_path("modules/{$n}/module.json")))
            ->values()
            ->all();
    }
}
