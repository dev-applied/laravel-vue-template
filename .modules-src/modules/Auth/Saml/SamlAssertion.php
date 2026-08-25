<?php

declare(strict_types=1);

namespace Modules\Auth\Saml;

use Illuminate\Support\Str;
use Modules\Auth\Sso\SsoIdentity;
use Modules\Auth\Sso\SsoRefused;
use OneLogin\Saml2\Auth as SamlAuth;

/**
 * A validated assertion, read into the protocol-neutral identity shape.
 *
 * Only ever called AFTER `$auth->isAuthenticated()` is true, which means the
 * signature, the audience, the destination and the time conditions have all
 * been checked. Everything below is reading, not deciding.
 */
class SamlAssertion
{
    public static function toIdentity(SamlAuth $auth): SsoIdentity
    {
        $attributes = $auth->getAttributes();
        $nameId     = (string) $auth->getNameId();

        $subject = self::subject($auth, $attributes, $nameId);
        $email   = self::email($attributes, $nameId);

        if ($email === '') {
            throw new SsoRefused(
                'SAML assertion for subject ['.$subject.'] carried no email address. '
                .'Attributes present: '.(implode(', ', array_keys($attributes)) ?: '(none)').'. '
                .'Set SAML_ATTR_EMAIL to the claim this IdP actually sends.'
            );
        }

        return new SsoIdentity(
            provider: SamlSettings::PROVIDER,
            id: $subject,
            email: $email,
            // TRUE, and this is the one place the two protocols legitimately
            // differ. For a social OIDC provider anyone can register an
            // address, so the provider's own `email_verified` claim is the only
            // evidence. A SAML IdP is different in kind: the project pasted
            // THIS IdP's signing certificate into its own config, and the
            // assertion is signed by it. The IdP is the system of record for
            // its users, so a signature-validated assertion IS the verification
            // — there is no weaker signal to fall back to.
            //
            // What that trust does NOT cover: an IdP asserting an address in a
            // domain it has no authority over. Federating with a partner's IdP
            // means trusting it for every address it cares to name, including
            // yours. SSO_ALLOWED_DOMAINS is the control, and the resolver
            // applies it to linking as well as registration for exactly this.
            emailVerified: true,
            firstName: self::attribute($attributes, 'first_name'),
            lastName: self::attribute($attributes, 'last_name'),
            raw: $attributes,
        );
    }

    /**
     * The stable identifier to key the local link on.
     *
     * NameID by default. A project can point SAML_ATTR_SUBJECT at something
     * else — worth doing when the IdP sends a transient or unspecified NameID,
     * which changes per session and would fork a new link every sign-in.
     *
     * @param  array<string, array<int, string>>  $attributes
     */
    protected static function subject(SamlAuth $auth, array $attributes, string $nameId): string
    {
        $configured = mb_trim((string) config('auth.saml.attributes.subject', ''));

        if ($configured !== '') {
            $value = self::firstValue($attributes, [$configured]);

            if ($value !== '') {
                return $value;
            }
        }

        return $nameId;
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    protected static function email(array $attributes, string $nameId): string
    {
        $email = self::firstValue($attributes, SamlSettings::attributeCandidates('email'));

        // Falling back to NameID only when it actually looks like an address.
        // Plenty of IdPs use an opaque NameID and carry the address in an
        // attribute; treating an opaque identifier as an email would create an
        // account named after a GUID.
        if ($email === '' && filter_var($nameId, FILTER_VALIDATE_EMAIL)) {
            $email = $nameId;
        }

        return Str::lower(mb_trim($email));
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    protected static function attribute(array $attributes, string $field): ?string
    {
        $value = self::firstValue($attributes, SamlSettings::attributeCandidates($field));

        return $value !== '' ? $value : null;
    }

    /**
     * First non-empty value among the candidate attribute names.
     *
     * SAML attributes are multi-valued by definition — every value is a list,
     * even single ones — so this flattens as it goes. The comparison is
     * case-insensitive on the attribute NAME because IdPs are inconsistent
     * about it (`mail` vs `Mail`, `givenName` vs `givenname`) and the
     * difference is never meaningful.
     *
     * @param  array<string, array<int, string>>  $attributes
     * @param  array<int, string>  $candidates
     */
    protected static function firstValue(array $attributes, array $candidates): string
    {
        $lowered = [];

        foreach ($attributes as $name => $values) {
            $lowered[mb_strtolower((string) $name)] = (array) $values;
        }

        foreach ($candidates as $candidate) {
            $values = $lowered[mb_strtolower($candidate)] ?? [];

            foreach ($values as $value) {
                if (mb_trim((string) $value) !== '') {
                    return mb_trim((string) $value);
                }
            }
        }

        return '';
    }
}
