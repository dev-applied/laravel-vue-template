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

        // Where the browser is sent once the provider round trip succeeds,
        // carrying a single-use handoff code the app redeems for a token. The
        // callback NEVER renders a token itself: a provider redirect is a
        // top-level navigation, and in a Capacitor build it lands in a system
        // browser the app cannot read. Always config-derived, never taken from
        // the request — a caller-supplied return target is an open redirect
        // that arrives holding a credential.
        'return_url' => env('SSO_RETURN_URL'),

        // PKCE on the authorization code. On by default: `start` is
        // unauthenticated and hands a valid `state` to anyone who asks, so
        // state alone does not bind a code to the request that produced it —
        // the verifier does. Turn off only for a provider that rejects
        // code_challenge (Google, Microsoft, Okta and Auth0 all accept it).
        'pkce' => env('SSO_PKCE', true),

        // Pin the ISSUER per provider, not just the email domain.
        //
        // Needed whenever a provider is multi-tenant — the `common` authority
        // on Microsoft Entra, an unrestricted Okta org, Google without a
        // hosted-domain restriction. On one of those, an attacker can create
        // their own tenant, mint a user carrying one of YOUR addresses, and
        // sign in through the same provider name you trust. `allowed_domains`
        // does not stop it: the domain is yours, which is precisely why it is
        // in the list. Only the tenant claim separates the two identities.
        //
        // Keyed by provider, then by claim. A claim may pin one value or a
        // list. An absent claim is refused, and so is a configured claim whose
        // expected value resolves empty — a pin that protects nothing while
        // looking like it does is worse than no pin at all.
        //
        //   'azure'  => ['tid' => env('SSO_AZURE_TENANT_ID')],
        //   'google' => ['hd'  => env('SSO_GOOGLE_HOSTED_DOMAIN')],
        //   'okta'   => ['iss' => env('SSO_OKTA_ISSUER')],
        //
        // Which claim carries it, and whether your Socialite driver surfaces
        // it at all, is driver-specific — log `$identity->raw` once against the
        // real provider and pin what is actually there.
        //
        // SAML needs no entry: php-saml already rejects an assertion whose
        // <Issuer> is not the configured SAML_IDP_ENTITY_ID.
        'required_claims' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SAML 2.0 (Auth module, `sso` option — the `saml` choices)
    |--------------------------------------------------------------------------
    |
    | A third choice on the same axis as `oidc`, not a variant of it: SAML is a
    | different protocol, where an enterprise IdP POSTs a signed XML assertion
    | to our ACS endpoint. Typical of AD FS, Azure, Okta and PingFederate.
    |
    | A project federates with ONE IdP, so there is no provider allow-list here
    | the way there is for OIDC — the IdP's certificate IS the allow-list.
    |
    | IMPORTANT: a signature-validated assertion is treated as proof the email
    | is verified, because the IdP is the system of record for its own users.
    | That trust does not extend to addresses the IdP has no authority over, so
    | when federating with someone else's IdP set SSO_ALLOWED_DOMAINS — the
    | resolver applies it to account LINKING as well as registration.
    |
    */

    'saml' => [
        'enabled' => env('SAML_ENABLED', false),
        // Shown on the login button: "Sign in with {label}".
        'label' => env('SAML_LABEL', 'SSO'),

        'idp' => [
            'entity_id' => env('SAML_IDP_ENTITY_ID'),
            'sso_url'   => env('SAML_IDP_SSO_URL'),
            'slo_url'   => env('SAML_IDP_SLO_URL'),
            // The IdP's signing certificate, base64 body. PEM headers and
            // newlines are stripped, so it can be pasted either way.
            'x509' => env('SAML_IDP_X509'),
        ],

        'sp' => [
            // Defaults to our own metadata URL, which is what most IdPs expect.
            'entity_id' => env('SAML_SP_ENTITY_ID'),
            // Only needed to SIGN AuthnRequests or to receive encrypted
            // assertions. Most integrations need neither.
            'x509'           => env('SAML_SP_X509'),
            'private_key'    => env('SAML_SP_PRIVATE_KEY'),
            'name_id_format' => env('SAML_SP_NAME_ID_FORMAT'),
        ],

        'security' => [
            'want_messages_signed'      => env('SAML_WANT_MESSAGES_SIGNED', false),
            'want_assertions_encrypted' => env('SAML_WANT_ASSERTIONS_ENCRYPTED', false),
            'sign_authn_requests'       => env('SAML_SIGN_AUTHN_REQUESTS', false),

            // OFF by default. An unsolicited assertion is one we never asked
            // for, which is the login-CSRF shape — an attacker replays an
            // assertion minted for their own account and the victim's browser
            // lands signed in as them. IdP-initiated sign-in (the tile in an
            // Okta or Azure dashboard) is a real requirement, so it is
            // available; turning it on accepts that the InResponseTo binding
            // no longer protects the flow.
            'allow_idp_initiated' => env('SAML_ALLOW_IDP_INITIATED', false),
        ],

        // Enterprise IdPs disagree about attribute naming more than any other
        // part of SAML. A sensible candidate list is tried (WS-Federation claim
        // URIs, urn:oid values, bare names); set these when yours uses
        // something else. `subject` is worth setting when the IdP sends a
        // transient NameID, which changes per session.
        'attributes' => [
            'email'      => env('SAML_ATTR_EMAIL', ''),
            'first_name' => env('SAML_ATTR_FIRST_NAME', ''),
            'last_name'  => env('SAML_ATTR_LAST_NAME', ''),
            'subject'    => env('SAML_ATTR_SUBJECT', ''),
        ],

        // Falls back to sso.return_url, then /auth/sso/complete. Both protocols
        // finish at the same page and redeem the same handoff code.
        'return_url' => env('SAML_RETURN_URL'),
    ],

];
