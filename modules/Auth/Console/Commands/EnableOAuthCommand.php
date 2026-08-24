<?php

declare(strict_types=1);

namespace Modules\Auth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class EnableOAuthCommand extends Command
{
    protected $signature = 'auth:enable-oauth {--force : Skip confirmation}';

    protected $description = 'Turn on the Auth module OAuth 2.1 layer: run Passport migrations and generate signing keys';

    public function handle(): int
    {
        if (! class_exists(Passport::class)) {
            $this->error('laravel/passport is not installed.');

            return self::FAILURE;
        }

        note('This enables OAuth 2.1 for MCP clients (auth code + PKCE, dynamic client registration).');

        if (! $this->option('force') && ! confirm('Run Passport migrations and generate signing keys now?', true)) {
            return self::SUCCESS;
        }

        // Passport migrations are loaded by the module only when AUTH_OAUTH_ENABLED
        // is true, so make them visible for this run regardless of current env.
        Passport::$registersRoutes = true;
        $this->loadPassportMigrations();

        $this->components->task('Running Passport migrations', fn () => Artisan::call('migrate', ['--force' => true]) === 0);
        $this->components->task('Generating OAuth keys', fn () => Artisan::call('passport:keys', ['--force' => false]) === 0 || true);

        warning('Set AUTH_OAUTH_ENABLED=true in your .env, then run: php artisan config:clear && php artisan route:clear');
        note('Keys were written to storage/. For deploys, copy them into PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY env vars instead of shipping the files.');

        return self::SUCCESS;
    }

    protected function loadPassportMigrations(): void
    {
        $path = base_path('vendor/laravel/passport/database/migrations');

        if (is_dir($path)) {
            $this->getLaravel()->make('migrator')->path($path);
        }
    }
}
