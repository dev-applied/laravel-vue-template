<?php

declare(strict_types=1);

namespace Modules\Auth\Sso;

use Illuminate\Support\Str;

/**
 * The allow-list of SSO providers this project accepts.
 *
 * Every entry point takes the provider as a URL segment, so without this the
 * segment would reach Socialite's driver factory directly and a caller could
 * name any driver the app happens to have configured. The list is explicit
 * config, never "whatever Socialite can resolve".
 */
class SsoProviders
{
    /**
     * Configured AND usable.
     *
     * The env default names a provider as a hint of what to set up, so listing
     * one whose credentials are absent is the normal state of a fresh install —
     * and it used to render a "Continue with Google" button that threw
     * DriverMissingConfigurationException the moment anyone pressed it. A
     * provider the project cannot actually complete a sign-in with is not
     * offered.
     *
     * @return array<int, string>
     */
    public static function enabled(): array
    {
        return collect(self::listed())
            ->filter(fn (string $p) => self::configured($p))
            ->values()
            ->all();
    }

    /** Named in config, regardless of whether credentials exist. */
    public static function listed(): array
    {
        return collect(explode(',', (string) config('auth.sso.providers', '')))
            ->map(fn (string $p) => Str::of($p)->trim()->lower()->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Socialite needs at least a client id and secret in config/services.php. */
    public static function configured(string $provider): bool
    {
        $service = config('services.'.Str::lower($provider), []);

        return is_array($service)
            && ! empty($service['client_id'])
            && ! empty($service['client_secret']);
    }

    public static function allows(string $provider): bool
    {
        return in_array(Str::lower($provider), self::enabled(), true);
    }

    public static function isEnabled(): bool
    {
        return (bool) config('auth.sso.enabled', false) && self::enabled() !== [];
    }

    /** Human label for the login button. */
    public static function label(string $provider): string
    {
        return match (Str::lower($provider)) {
            'google'             => 'Google',
            'microsoft', 'azure' => 'Microsoft',
            'github'             => 'GitHub',
            'gitlab'             => 'GitLab',
            'okta'               => 'Okta',
            default              => Str::headline($provider),
        };
    }
}
