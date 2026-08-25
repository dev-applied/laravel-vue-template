<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent user provider.
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Passport-backed OAuth guard. Dormant until the Auth module's OAuth
        // layer is enabled (AUTH_OAUTH_ENABLED=true) — the /mcp endpoint then
        // accepts OAuth access tokens through it alongside Sanctum PATs.
        'api' => [
            'driver'   => 'passport',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | If you have multiple user tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | You may specify multiple password reset configurations if you have more
    | than one user table or model in the application and you want to have
    | separate password reset settings based on the specific user types.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | times out and the user is prompted to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => 10800,

    /*
    |--------------------------------------------------------------------------
    | MCP + OAuth (Auth module)
    |--------------------------------------------------------------------------
    |
    | The Auth module registers the /mcp endpoint and an optional OAuth 2.1
    | layer. Endpoint auth is `auth:sanctum` (bearer PAT) until oauth.enabled,
    | which adds Passport OAuth and widens the guard to `auth:sanctum,api`.
    | See docs/Authentication.md.
    |
    */

    'mcp' => [
        'enabled' => env('AUTH_MCP_ENABLED', true),
        'path'    => env('AUTH_MCP_PATH', 'mcp'),
    ],

    'oauth' => [
        'enabled' => env('AUTH_OAUTH_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Single sign-on (Auth module, `sso` option)
    |--------------------------------------------------------------------------
    |
    | A SEPARATE axis from `oauth` above, and the opposite direction: `oauth`
    | makes this app an OAuth SERVER for MCP clients, while `sso` makes it an
    | OAuth CLIENT of Google / Microsoft / Okta. A project can want either, both
    | or neither, so they are not choices on one setting.
    |
    | `providers` is an allow-list, not a hint: the provider arrives as a URL
    | segment, and without the list it would reach Socialite's driver factory
    | directly. Each named provider still needs its own credentials in
    | config/services.php.
    |
    | `allow_registration` is off by default. On an open provider like Google,
    | on means anyone with an address can create an account; `allowed_domains`
    | narrows that to a comma-separated list of email domains when it is on.
    |
    */

    'sso' => [
        'enabled'            => env('SSO_ENABLED', false),
        'providers'          => env('SSO_PROVIDERS', ''),
        'allow_registration' => env('SSO_ALLOW_REGISTRATION', false),
        'allowed_domains'    => env('SSO_ALLOWED_DOMAINS', ''),
        // Overrides the derived /api/v1/auth/sso/{provider}/callback — needed
        // when the provider must redirect somewhere the API is not, e.g. a
        // deep-link host for a Capacitor build.
        'callback_url' => env('SSO_CALLBACK_URL'),
    ],

];
