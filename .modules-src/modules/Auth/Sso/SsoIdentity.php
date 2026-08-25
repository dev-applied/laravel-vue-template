<?php

declare(strict_types=1);

namespace Modules\Auth\Sso;

use Illuminate\Support\Str;

/**
 * One identity, normalised, from whichever protocol produced it.
 *
 * The resolver used to take a Socialite user directly. That tied the project's
 * extension seam to an OIDC library — a project overriding the resolver had to
 * import Socialite, and the SAML option, which has no Socialite anywhere in it,
 * could not reach the resolver at all. This is the protocol-neutral shape both
 * halves build.
 *
 * `emailVerified` is a required constructor argument on purpose. It is the one
 * fact the account-linking decision turns on, and the previous design inferred
 * it from raw claims at the point of use — which is how the registration path
 * came to skip the check entirely.
 */
class SsoIdentity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly array $raw = [],
    ) {}

    /**
     * Build from a Socialite user.
     *
     * Socialite normalises only id/name/email/avatar, so verification is read
     * from the raw claim — and a MISSING claim counts as unverified rather than
     * assuming the best.
     *
     * Only the two claims that unambiguously mean "this email address was
     * verified" are consulted. A bare `verified` was previously accepted too,
     * and it means something else entirely on several providers (a blue check
     * on X, account standing on Facebook) — a trapdoor waiting for whoever
     * added the next driver.
     *
     * @param  object  $user  a Laravel\Socialite\Contracts\User
     */
    public static function fromSocialite(string $provider, object $user): self
    {
        $raw = method_exists($user, 'getRaw') ? (array) $user->getRaw() : [];

        $verified = false;

        foreach (['email_verified', 'verified_email'] as $claim) {
            if (array_key_exists($claim, $raw)) {
                $verified = filter_var($raw[$claim], FILTER_VALIDATE_BOOLEAN);

                break;
            }
        }

        $name = (string) ($user->getName() ?: $user->getNickname() ?: '');

        return new self(
            provider: $provider,
            id: (string) $user->getId(),
            email: Str::lower(mb_trim((string) $user->getEmail())),
            emailVerified: $verified,
            firstName: $name !== '' ? (Str::before($name, ' ') ?: $name) : null,
            lastName: $name !== '' ? (Str::after($name, ' ') ?: null) : null,
            raw: $raw,
        );
    }

    /** A display name, falling back to the local part of the address. */
    public function displayName(): string
    {
        $name = mb_trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return $name !== '' ? $name : Str::before($this->email, '@');
    }
}
