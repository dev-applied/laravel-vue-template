<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

/**
 * Guided setup for a project freshly cloned from the template.
 *
 * SKELETON — the guided checklist is real; the automated steps grow as the
 * module system matures. Module copy-in is the `module:add` artisan command
 * (it pulls from the firm modules repo via the GitHub API or --from).
 */
class ProjectInit extends Command
{
    protected $signature = 'project:init';

    protected $description = 'Guided first-run setup: app naming, module selection, and the project-birth checklist';

    public function handle(): int
    {
        info('Applied Imagination project init');

        $appName = text(
            label: 'Project (app) name',
            placeholder: 'e.g. Washwerk',
            default: (string) config('app.name'),
            required: true,
        );

        $this->rewriteEnv('APP_NAME', $appName);
        $this->components->info("APP_NAME set to {$appName} in .env");

        // ── Modules ──────────────────────────────────────────────────────
        $installed = array_map('basename', glob(base_path('modules/*'), GLOB_ONLYDIR) ?: []);
        note('Installed modules: '.($installed === [] ? '(none)' : implode(', ', $installed)));

        // module:add prompts each module's options (e.g. Auth: Sanctum vs
        // Sanctum + Passport OAuth) and installs deps accordingly.
        if (confirm('Add modules from the firm modules repo now?', default: true)) {
            $this->call('module:add');
        }

        if (confirm('Run migrations now (includes module migrations)?', default: true)) {
            $this->call('migrate');
        }

        // ── The rest of the birth checklist ──────────────────────────────
        note(<<<'CHECKLIST'
            Remaining project-birth checklist (see docs/modules.md and the client-deploy runbook):
              [ ] .env: APP_DOMAIN / DOCKER_* values for this project
              [ ] visilaunch/Vaultwarden: create the client project + wire the GitHub environment-secret sync
              [ ] GitHub: deploy caller workflows pass `secrets: inherit`
              [ ] composer typescript  (after any module add — and `php artisan route:clear` BEFORE any npm build)
              [ ] Remove the Example module if this client does not want the reference: rm -rf modules/Example
            CHECKLIST);

        return self::SUCCESS;
    }

    private function rewriteEnv(string $key, string $value): void
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            $this->components->warn('.env not found — skipped APP_NAME rewrite');

            return;
        }

        $quoted   = str_contains($value, ' ') ? '"'.$value.'"' : $value;
        $contents = (string) file_get_contents($path);
        $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', "{$key}={$quoted}", $contents, 1, $count);

        if ($count === 0) {
            $contents .= PHP_EOL."{$key}={$quoted}".PHP_EOL;
        }
        file_put_contents($path, $contents);
    }
}
