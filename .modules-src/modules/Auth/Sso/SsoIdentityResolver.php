<?php

declare(strict_types=1);

namespace Modules\Auth\Sso;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Auth\Models\UserSsoIdentity;
use Throwable;

/**
 * Turns a provider identity into a local user.
 *
 * The seam: a project binds its own implementation of this class to apply its
 * own rules (per-tenant provider config, role mapping from claims, group
 * membership). The default below is deliberately the CONSERVATIVE one — it
 * refuses anything it cannot prove, and every refusal is an {@see SsoRefused}
 * whose detail goes to the log rather than to the caller.
 *
 * The trust model, stated plainly because getting it wrong is an account
 * takeover: an assertion is only worth what the party making it is worth. A
 * provider says "this subject controls this email address"; whether that is
 * true depends on whether the provider verifies addresses AND on whether the
 * *specific* issuer is one this project trusts for that domain. The second half
 * is not something this class can check — see the note on `domainAllowed()`.
 */
class SsoIdentityResolver
{
    private ?bool $hasDeactivatedColumn = null;

    public function resolve(string $provider, SsoIdentity $identity): User
    {
        if ($identity->id === '') {
            throw new SsoRefused("Provider [{$provider}] returned no subject identifier.");
        }

        // Before ANY path below, including the already-linked one. A pin added
        // after the fact must revoke identities that got in before it — that is
        // the whole reason to add one — and checking only on first link would
        // leave exactly the identity you are trying to remove signing in.
        $this->assertClaimsPinned($provider, $identity);

        // 1. An already-linked identity is the only unambiguous case: this exact
        //    subject at this exact provider has been seen before.
        $link = UserSsoIdentity::query()
            ->where('provider', $provider)
            ->where('provider_id', $identity->id)
            ->first();

        if ($link !== null && $link->user !== null) {
            $user = $this->assertUsable($link->user);

            $this->touchLink($user, $provider, $identity);

            return $user;
        }

        if ($identity->email === '') {
            throw new SsoRefused("Provider [{$provider}] returned no email address for subject [{$identity->id}].");
        }

        // Indexed equality, not LOWER(email): the address is already lowercased
        // on the way in, `users.email` is unique, and MySQL's default collation
        // is case-insensitive. The functional comparison it replaces forced a
        // full table scan on every unauthenticated callback.
        $existing = User::query()->where('email', $identity->email)->first();

        if ($existing !== null) {
            // 2. Linking a provider identity to an existing password account on
            //    the strength of a matching email is an account takeover unless
            //    the provider ASSERTS the address is verified — otherwise anyone
            //    who can set an unverified email at the provider inherits the
            //    account.
            if (! $identity->emailVerified) {
                throw new SsoRefused(
                    "Provider [{$provider}] did not assert [{$identity->email}] is verified; refused to link to existing user [{$existing->getKey()}]."
                );
            }

            // The domain allow-list now gates LINKING as well as registration.
            // It previously gated registration only, which left the more
            // dangerous half open: on a multi-tenant provider an attacker can
            // stand up their own tenant, mint a user with a victim's address,
            // mark it verified in their own claim mapping, and be linked
            // straight onto the victim's account. Restricting which domains may
            // arrive at all is the control a project actually has.
            if (! $this->domainAllowed($identity->email)) {
                throw new SsoRefused(
                    "Domain for [{$identity->email}] is not in the SSO allow-list; refused to link to existing user [{$existing->getKey()}]."
                );
            }

            $user = $this->assertUsable($existing);

            // assertUsable FIRST, then write. The other order left a refused,
            // deactivated account with a freshly created identity row and a
            // bumped last_login_at — an identity pre-attached for whenever the
            // account is reactivated, and a login timestamp for a login that
            // never happened, in the exact field a dormant-account audit reads.
            $this->touchLink($user, $provider, $identity);

            return $user;
        }

        // 3. Creating accounts from an SSO login is off unless a project turns
        //    it on: on an open provider like Google it means anyone with an
        //    address can create an account.
        if (! config('auth.sso.allow_registration', false)) {
            throw new SsoRefused("No local account for [{$identity->email}] and SSO registration is disabled.");
        }

        // Registration demands a verified address for the SAME reason linking
        // does, and this check was missing. Without it an identity asserting an
        // unverified `cfo@client.com` got a local account bearing that address,
        // stamped email_verified_at = now() — which every later invite-by-email
        // or verified-user gate then treats as genuine, and which the real
        // owner is linked onto the moment they sign in.
        if (! $identity->emailVerified) {
            throw new SsoRefused(
                "Provider [{$provider}] did not assert [{$identity->email}] is verified; refused to register a new account."
            );
        }

        if (! $this->domainAllowed($identity->email)) {
            throw new SsoRefused("Domain for [{$identity->email}] is not in the SSO allow-list; refused to register.");
        }

        // One transaction: a user created without its identity row is an
        // account nobody can reach. The link lookup misses it, the email lookup
        // finds it, and linking then demands proof the provider may never send
        // — so the row exists, blocks registration, and can never be signed
        // into.
        return DB::transaction(function () use ($provider, $identity) {
            $user = $this->createUser($identity);

            $this->touchLink($user, $provider, $identity);

            return $user;
        });
    }

    /**
     * Pin the ISSUER, not just the domain.
     *
     * The domain allow-list is a weaker control than it looks against a
     * multi-tenant OIDC endpoint, and this is the published nOAuth shape: an
     * attacker stands up their own Entra tenant, creates a user whose mail is
     * `cfo@yourclient.com`, and signs in through the SAME provider name your
     * app trusts. `provider` matches. The domain is in your allow-list — it is
     * YOUR domain, that is why it is listed. Every check the resolver has left
     * passes, and the identity links onto the real CFO's account.
     *
     * What separates the two is which tenant asserted it, and that only ever
     * appears in a claim: `tid` on Microsoft Entra, `hd` on Google Workspace,
     * `iss` on a generic OIDC provider. The module cannot know which claim a
     * given Socialite driver surfaces or what value is correct for a project,
     * so the project names both:
     *
     *     'required_claims' => ['azure' => ['tid' => env('SSO_AZURE_TENANT_ID')]],
     *
     * Three rules, all of them fail-closed:
     *
     *  - No entry for a provider means no pin. Single-tenant providers and ones
     *    you operate yourself do not need one.
     *  - A claim that is ABSENT from the identity is a refusal, never a pass.
     *    An attacker who can choose the issuer can usually also choose which
     *    optional claims come with it, so "missing" must not mean "fine".
     *  - A configured claim whose expected value is empty is a refusal too. It
     *    means an env var did not resolve, and the alternative is a pin that
     *    silently protects nothing while reading as though it does.
     *
     * SAML needs nothing here: php-saml compares the assertion's `<Issuer>` to
     * the configured `idp.entityId` and rejects a mismatch before the assertion
     * is ever read, so that protocol is issuer-pinned by construction. The
     * mechanism still works for it — `raw` is the attribute set — if a project
     * wants to require a specific attribute on top.
     */
    protected function assertClaimsPinned(string $provider, SsoIdentity $identity): void
    {
        /** @var array<string, mixed> $required */
        $required = (array) config("auth.sso.required_claims.{$provider}", []);

        if ($required === []) {
            return;
        }

        foreach ($required as $claim => $expected) {
            $expected = collect(is_array($expected) ? $expected : [$expected])
                ->map(fn ($value) => Str::of((string) $value)->trim()->lower()->toString())
                ->filter()
                ->values();

            if ($expected->isEmpty()) {
                throw new SsoRefused(
                    "SSO claim pin [{$provider}.{$claim}] is configured with no expected value — "
                    .'refusing every sign-in for this provider until it is set or removed. '
                    .'This is almost always an env var that did not resolve.'
                );
            }

            // Dot notation so a nested claim can be pinned, e.g. `organization.id`.
            $actual = data_get($identity->raw, $claim);

            if (is_array($actual) || is_object($actual)) {
                throw new SsoRefused(
                    "SSO claim [{$claim}] on provider [{$provider}] is not a scalar; cannot pin it."
                );
            }

            $actual = Str::of((string) ($actual ?? ''))->trim()->lower()->toString();

            if ($actual === '') {
                throw new SsoRefused(
                    "Provider [{$provider}] did not send the pinned claim [{$claim}] for [{$identity->email}]. "
                    .'Claims present: '.(implode(', ', array_keys($identity->raw)) ?: '(none)').'.'
                );
            }

            if (! $expected->contains($actual)) {
                throw new SsoRefused(
                    "Provider [{$provider}] asserted [{$claim}={$actual}] for [{$identity->email}], "
                    .'which is not a pinned value. This is what an identity from an untrusted '
                    .'tenant looks like.'
                );
            }
        }
    }

    /**
     * Which email domains may sign in at all.
     *
     * This is the only lever a project has against an untrustworthy ISSUER. The
     * allow-list here says nothing about WHICH tenant or organisation asserted
     * the address — a provider name is not an issuer. A project federating with
     * a multi-tenant endpoint (the `common` authority on Microsoft Entra, an
     * unrestricted Okta org) must ALSO pin the issuer in its own
     * `config/services.php`, or override this class and check the tenant claim.
     * Set SSO_ALLOWED_DOMAINS whenever the provider is not one you operate.
     */
    protected function domainAllowed(string $email): bool
    {
        $allowed = collect(explode(',', (string) config('auth.sso.allowed_domains', '')))
            ->map(fn (string $d) => Str::of($d)->trim()->lower()->ltrim('@')->toString())
            ->filter();

        // No list configured means no domain restriction.
        if ($allowed->isEmpty()) {
            return true;
        }

        return $allowed->contains(Str::after($email, '@'));
    }

    protected function assertUsable(User $user): User
    {
        if ($this->deactivationIsTracked() && $user->getAttribute('deactivated_at') !== null) {
            throw new SsoRefused("User [{$user->getKey()}] is deactivated; refused SSO sign-in.");
        }

        return $user;
    }

    /**
     * Whether `users.deactivated_at` exists — the column the Users module adds.
     *
     * A clean false means the module is not installed and there is no gate to
     * apply. A THROWN failure means we could not tell, and that fails closed:
     * a schema-cache miss or an information_schema permission problem must not
     * quietly turn off a deactivation check. Memoised per instance (one per
     * request) so it costs one introspection query, not one per call.
     */
    protected function deactivationIsTracked(): bool
    {
        if ($this->hasDeactivatedColumn !== null) {
            return $this->hasDeactivatedColumn;
        }

        try {
            return $this->hasDeactivatedColumn = Schema::hasColumn('users', 'deactivated_at');
        } catch (Throwable $e) {
            report($e);

            throw new SsoRefused('Could not determine whether accounts are deactivatable: '.$e->getMessage());
        }
    }

    protected function createUser(SsoIdentity $identity): User
    {
        $user             = new User;
        $user->first_name = $identity->firstName ?: $identity->displayName();
        $user->last_name  = $identity->lastName ?: '';
        $user->email      = $identity->email;
        // A random unusable hash, never null: a null password column makes
        // "has no password" and "password not set yet" indistinguishable, and
        // some guards treat null as a match.
        $user->password = bcrypt(Str::random(64));
        // Only reachable once emailVerified is true (checked above), so this
        // records a verification that actually happened.
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * The unique index on (provider, provider_id) — not this method — is what
     * makes an identity singular. updateOrCreate is select-then-insert, so two
     * simultaneous first-time sign-ins for the same subject race; the index
     * turns the loser into a failed request rather than a duplicate link,
     * which is the correct way to lose.
     */
    protected function touchLink(User $user, string $provider, SsoIdentity $identity): void
    {
        UserSsoIdentity::query()->updateOrCreate(
            ['provider' => $provider, 'provider_id' => $identity->id],
            ['user_id' => $user->getKey(), 'email' => $identity->email, 'last_login_at' => now()],
        );
    }
}
