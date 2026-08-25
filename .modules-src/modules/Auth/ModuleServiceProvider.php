<?php

declare(strict_types=1);

namespace Modules\Auth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Passport;
use Modules\Auth\Console\Commands\EnableOAuthCommand;
use Modules\Auth\Mail\ForgotPasswordMail;
use Modules\Auth\Mcp\AppServer;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Suppress Passport's /oauth/* routes when the OAuth layer is off. This
        // MUST happen in register() — PassportServiceProvider::boot() reads the
        // flag during the boot phase, after every provider's register() has run.
        // Passport is installed only for the optional layer; a Sanctum-only
        // project shouldn't advertise OAuth endpoints.
        if (! config('auth.oauth.enabled', false) && class_exists(Passport::class)) {
            Passport::ignoreRoutes();
        }
    }

    public function boot(): void
    {
        $this->registerImpersonationGate();

        // The module's own migrations. Guarded because the sso=none variant
        // deletes the only file in here and module:add then prunes the empty
        // directory.
        if (is_dir(__DIR__.'/Database/Migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        }

        $this->loadViewsFrom(__DIR__.'/resources/views', 'auth');

        // The password-reset mail belongs to this module. The kernel User model
        // keeps the framework's CanResetPassword trait untouched; this hook
        // swaps the notification's mailable for ours. The mailable must address
        // itself — the mail channel sends a returned Mailable as-is.
        ResetPassword::toMailUsing(
            fn ($notifiable, string $token) => (new ForgotPasswordMail($token, $notifiable))->to($notifiable)
        );

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Routes/api.php');

        $this->bootMcp();

        // EnableOAuthCommand ships only with the sanctum+oauth option; the
        // sanctum-only install drops the file, so guard on its presence.
        if ($this->app->runningInConsole() && class_exists(EnableOAuthCommand::class)) {
            $this->commands([EnableOAuthCommand::class]);
        }
    }

    /**
     * Register the MCP server endpoint and, when enabled, the OAuth 2.1 layer.
     *
     * Two auth modes, one endpoint:
     *   - Default: `auth:sanctum` — an MCP client sends a Personal Access
     *     Token as a bearer. Zero OAuth moving parts. This is the shipped
     *     default and covers most projects.
     *   - AUTH_OAUTH_ENABLED=true: adds Passport OAuth 2.1 (auth code + PKCE,
     *     Dynamic Client Registration) for MCP clients that speak OAuth, and
     *     widens the endpoint guard to `auth:sanctum,api` so both token kinds
     *     work. See docs/Authentication.md for the one-time enable steps.
     */
    protected function bootMcp(): void
    {
        if (! config('auth.mcp.enabled', true) || ! class_exists(Mcp::class)) {
            return;
        }

        $oauthEnabled = (bool) config('auth.oauth.enabled', false);
        $path         = (string) config('auth.mcp.path', 'mcp');
        $guard        = $oauthEnabled ? 'auth:sanctum,api' : 'auth:sanctum';

        Mcp::web($path, AppServer::class)->middleware($guard);

        if ($oauthEnabled) {
            $this->bootOAuth();
        }
        // (Passport's /oauth/* routes are suppressed in register() when the
        // layer is off — it must run before PassportServiceProvider::boot().)
    }

    protected function bootOAuth(): void
    {
        // OAuth tables live behind the flag: only load Passport's migrations
        // when the layer is on, so a Sanctum-only project's `migrate` stays clean.
        $this->loadMigrationsFrom(base_path('vendor/laravel/passport/database/migrations'));

        // The consent screen shown at /oauth/authorize (module-owned, themed).
        Passport::authorizationView('auth::oauth.authorize');

        // Discovery (.well-known/*) + RFC 7591 Dynamic Client Registration at
        // /oauth/register, so MCP clients self-register. Passport's own
        // /oauth/authorize + /oauth/token back it.
        Mcp::oauthRoutes();

        // The consent page needs a web session. A guest who reaches
        // /oauth/authorize is bounced to the SPA login, which — with OAuth on —
        // also establishes the web session (see AuthController::login), then
        // returns here via ?to=.
        AuthenticationException::redirectUsing(
            fn ($request) => url('/login?to='.urlencode($request->fullUrl()))
        );
    }

    /**
     * Impersonation denies by default, and says so.
     *
     * The endpoint mints a working bearer token for ANY user id. It previously
     * carried `auth:sanctum` and nothing else, which made it a one-request
     * account takeover: the lowest-privilege account posts `{"user_id": 1}` and
     * receives the super-admin's token.
     *
     * Fail-closed, copying the shape `modules/Users` uses. If a project has not
     * defined the gate, the answer is no and a log line explains why — the
     * alternative, an undefined gate meaning "allow", is how the original hole
     * would come back the moment someone deleted a line in AppServiceProvider.
     */
    protected function registerImpersonationGate(): void
    {
        if (Gate::has('impersonate-users')) {
            return;
        }

        Gate::define('impersonate-users', function (): bool {
            Log::warning(
                'modules/Auth: no `impersonate-users` gate is defined, so impersonation is denied to '
                .'everyone. Define it in AppServiceProvider::boot() — see modules/Auth/README.md.'
            );

            return false;
        });
    }
}
