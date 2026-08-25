<?php

declare(strict_types=1);

namespace Modules\Auth\Saml;

use Illuminate\Support\Str;
use OneLogin\Saml2\Settings;
use RuntimeException;
use Throwable;

/**
 * Translates config/auth.php `saml` into the php-saml settings array.
 *
 * Everything the toolkit needs is derived here rather than sniffed from
 * $_SERVER. `baseurl` is set explicitly for exactly that reason: php-saml
 * otherwise reconstructs the current URL from HTTP_HOST / REQUEST_URI to check
 * the assertion's Destination, and behind a proxy (which every deployment of
 * this template is — Traefik in dev, an ALB in production) that reconstruction
 * is wrong. A mismatched Destination reads as a forged assertion, so this fails
 * closed in a way that looks like an attack rather than a misconfiguration.
 */
class SamlSettings
{
    public const PROVIDER = 'saml';

    /** ACS and metadata paths, kept in one place so routes and settings agree. */
    public const ACS_PATH = 'api/v1/auth/saml/acs';

    public const SLS_PATH = 'api/v1/auth/saml/sls';

    public const METADATA_PATH = 'api/v1/auth/saml/metadata';

    public static function enabled(): bool
    {
        return (bool) config('auth.saml.enabled', false);
    }

    /**
     * Enabled AND actually completable.
     *
     * Same rule the OIDC provider list learned the hard way: a login button for
     * an IdP whose entity id / SSO URL / certificate are absent throws the
     * moment someone presses it. An install that cannot finish a sign-in does
     * not advertise one.
     */
    public static function configured(): bool
    {
        $idp = (array) config('auth.saml.idp', []);

        return self::enabled()
            && ! empty($idp['entity_id'])
            && ! empty($idp['sso_url'])
            && ! empty($idp['x509']);
    }

    /** The label a login button shows: "Sign in with {label}". */
    public static function label(): string
    {
        $label = mb_trim((string) config('auth.saml.label', ''));

        return $label !== '' ? $label : 'SSO';
    }

    /**
     * Settings for Single Logout, which has a stricter floor than sign-in.
     *
     * `wantMessagesSigned` is optional for sign-in and correctly so: the
     * assertion carries its own signature, and most IdPs sign the assertion
     * without signing the Response wrapper around it. Turning it on globally
     * would break them for no gain.
     *
     * A LogoutRequest has no assertion. There is nothing inside it to sign, so
     * the message signature is the ONLY authentication the message has — and
     * php-saml requires it only when this flag is set (LogoutRequest.php:428:
     * an absent Signature is simply not checked when the flag is off). Left at
     * the default, the SLS endpoint would accept an unsigned LogoutRequest
     * naming any NameID at all, from anyone: an unauthenticated log-anybody-out.
     *
     * So it is forced here and is not configurable, for the same reason
     * `wantAssertionsSigned` is. The same applies to a LogoutResponse coming
     * back — also assertion-free, also only as trustworthy as its signature.
     *
     * @return array<string, mixed>
     */
    public static function forSlo(): array
    {
        $settings = self::toArray();

        $settings['security']['wantMessagesSigned'] = true;

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        $idp = (array) config('auth.saml.idp', []);
        $sp  = (array) config('auth.saml.sp', []);
        $sec = (array) config('auth.saml.security', []);

        $signRequests  = (bool) ($sec['sign_authn_requests'] ?? false);
        $wantEncrypted = (bool) ($sec['want_assertions_encrypted'] ?? false);

        return [
            // Forces php-saml to build its own URLs from the app URL instead of
            // reading the (proxied) request. See the class docblock.
            'baseurl' => mb_rtrim((string) config('app.url'), '/'),
            'strict'  => true,
            'debug'   => false,

            'sp' => [
                'entityId'                 => (string) ($sp['entity_id'] ?: url(self::METADATA_PATH)),
                'assertionConsumerService' => [
                    'url'     => url(self::ACS_PATH),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'singleLogoutService' => [
                    'url'     => url(self::SLS_PATH),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                // Unspecified by default: pinning emailAddress makes IdPs that
                // key on an opaque immutable id (Azure AD, PingFederate) reject
                // the request outright, and the email is read from attributes
                // anyway.
                'NameIDFormat' => (string) ($sp['name_id_format'] ?: 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified'),
                'x509cert'     => (string) ($sp['x509'] ?? ''),
                'privateKey'   => (string) ($sp['private_key'] ?? ''),
            ],

            'idp' => [
                'entityId'            => (string) ($idp['entity_id'] ?? ''),
                'singleSignOnService' => [
                    'url'     => (string) ($idp['sso_url'] ?? ''),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'singleLogoutService' => [
                    'url'     => (string) ($idp['slo_url'] ?? ''),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => (string) ($idp['x509'] ?? ''),
            ],

            'security' => [
                // The one non-negotiable: an unsigned assertion is an assertion
                // anyone can write. Everything else here has a defensible off
                // position; this does not.
                'wantAssertionsSigned' => true,

                'wantMessagesSigned'      => (bool) ($sec['want_messages_signed'] ?? false),
                'wantAssertionsEncrypted' => $wantEncrypted,
                'wantNameId'              => false,
                'wantNameIdEncrypted'     => false,

                'authnRequestsSigned'  => $signRequests,
                'logoutRequestSigned'  => $signRequests,
                'logoutResponseSigned' => $signRequests,
                'signMetadata'         => false,

                'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digestAlgorithm'    => 'http://www.w3.org/2001/04/xmlenc#sha256',

                // SHA-1 is forgeable in this context; refuse it rather than warn.
                'rejectDeprecatedAlgorithm' => true,

                // An unsolicited response is one we never asked for, which is
                // precisely the login-CSRF shape: an attacker replays an
                // assertion minted for their own account and the victim's
                // browser lands signed in as them. IdP-initiated sign-in is a
                // real enterprise requirement, so it is available — but it is
                // opt-in, and the project turning it on is choosing to accept
                // that the InResponseTo binding no longer protects them.
                'rejectUnsolicitedResponsesWithInResponseTo' => ! (bool) ($sec['allow_idp_initiated'] ?? false),

                'requestedAuthnContext' => false,
            ],
        ];
    }

    /**
     * Validated settings, or a RuntimeException whose message names what is
     * missing. php-saml's own exception for this is an array of error codes.
     */
    public static function build(): Settings
    {
        if (! self::configured()) {
            throw new RuntimeException('SAML single sign-on is not finished being set up.');
        }

        try {
            return new Settings(self::toArray());
        } catch (Throwable $e) {
            throw new RuntimeException('The SAML configuration is not valid: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Settings for the metadata endpoint, which must work BEFORE an IdP is
     * configured — an IdP administrator needs our metadata in order to give us
     * the values `configured()` is checking for. Chicken and egg otherwise.
     */
    public static function buildForMetadata(): Settings
    {
        $config = self::toArray();

        // spValidationOnly: don't demand idp entityId / SSO URL / certificate
        // just to describe ourselves.
        return new Settings($config, true);
    }

    /**
     * Where the browser is sent after a successful ACS, with the one-time
     * handoff code appended. Configurable because a Capacitor build lands on a
     * deep-link host rather than the API's own origin.
     */
    public static function returnUrl(): string
    {
        $configured = mb_trim((string) config('auth.saml.return_url', ''));

        return $configured !== '' ? $configured : url('/auth/saml/complete');
    }

    /**
     * Candidate attribute names for a field, most specific first.
     *
     * Enterprise IdPs disagree about attribute naming more than any other part
     * of SAML: AD FS and Azure emit WS-Federation claim URIs, anything
     * LDAP-backed emits urn:oid values, and hand-rolled IdPs emit bare names.
     * A project pins one with SAML_ATTR_EMAIL when its IdP uses something else
     * entirely.
     *
     * @return array<int, string>
     */
    public static function attributeCandidates(string $field): array
    {
        $configured = mb_trim((string) config("auth.saml.attributes.{$field}", ''));

        $defaults = match ($field) {
            'email' => [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
                'urn:oid:0.9.2342.19200300.100.1.3',
                'urn:oid:1.2.840.113549.1.9.1',
                'mail',
                'email',
                'emailAddress',
                'EmailAddress',
                'User.Email',
            ],
            'first_name' => [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
                'urn:oid:2.5.4.42',
                'givenName',
                'first_name',
                'firstName',
                'FirstName',
                'User.FirstName',
            ],
            'last_name' => [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
                'urn:oid:2.5.4.4',
                'sn',
                'surname',
                'last_name',
                'lastName',
                'LastName',
                'User.LastName',
            ],
            default => [],
        };

        return $configured !== ''
            ? array_values(array_unique([$configured, ...$defaults]))
            : $defaults;
    }

    /** Strips PEM headers/whitespace so a cert can be pasted into .env either way. */
    public static function normalizeCert(?string $cert): string
    {
        return (string) Str::of((string) $cert)
            ->replaceMatches('/-----(BEGIN|END) CERTIFICATE-----/', '')
            ->replaceMatches('/\s+/', '');
    }
}
