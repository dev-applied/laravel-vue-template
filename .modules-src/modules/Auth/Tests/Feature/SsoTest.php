<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Modules\Auth\Models\UserSsoIdentity;

/**
 * Every test here is a security property. SSO is a second front door onto every
 * account, so "it signs people in" is the least interesting thing about it.
 *
 * Shape to keep in mind while reading: the callback is a REDIRECT, not a JSON
 * endpoint. The browser is sitting on it, so it hands back a single-use handoff
 * code in the URL and the app redeems that for a token over a back channel.
 */
beforeEach(function () {
    config([
        'auth.sso.enabled'            => true,
        'auth.sso.providers'          => 'google',
        'auth.sso.allow_registration' => false,
        'auth.sso.allowed_domains'    => '',
        'auth.sso.return_url'         => 'https://app.test/auth/sso/complete',
        // A provider is only offered once it has credentials, so the fixtures
        // need them even though Socialite itself is mocked for the callback.
        'services.google' => [
            'client_id'     => 'test-id',
            'client_secret' => 'test-secret',
            'redirect'      => 'https://example.test/callback',
        ],
    ]);
});

/** A Socialite user with controllable raw claims. */
function ssoIdentity(string $id, string $email, bool $verified = true, string $name = 'Ada Lovelace', array $claims = []): SocialiteUser
{
    $user        = new SocialiteUser;
    $user->id    = $id;
    $user->name  = $name;
    $user->email = $email;
    $user->user  = ['email_verified' => $verified] + $claims;

    return $user;
}

/** Mint a state the way `start` does, so `callback` accepts it. */
function ssoState(string $provider = 'google', string $verifier = 'test-verifier'): string
{
    $state = 'test-state-'.uniqid();

    Cache::put('sso:state:'.hash('sha256', $state), [
        'provider' => $provider,
        'verifier' => $verifier,
        'ip'       => '127.0.0.1',
    ], 600);

    return $state;
}

/**
 * Mocked against the real provider class, not a bare Mockery mock: the
 * controller asks `method_exists($driver, 'enablePKCE')` before enabling PKCE,
 * and a classless mock answers false — which would silently skip the code path
 * these tests exist to cover.
 */
function fakeSocialite(SocialiteUser $identity): void
{
    $driver = Mockery::mock(GoogleProvider::class);
    $driver->shouldReceive('stateless')->andReturnSelf();
    $driver->shouldReceive('redirectUrl')->andReturnSelf();
    $driver->shouldReceive('with')->andReturnSelf();
    $driver->shouldReceive('enablePKCE')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($identity);

    Socialite::shouldReceive('driver')->andReturn($driver);
}

/** Follow the callback and pull the handoff code out of the redirect. */
function handoffCode(string $location): ?string
{
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query['code'] ?? null;
}

function handoffError(string $location): ?string
{
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query['error'] ?? null;
}

// ---------------------------------------------------------------------------
// Discovery / allow-list
// ---------------------------------------------------------------------------

test('an unconfigured provider is unknown, not forbidden', function () {
    // 404 rather than 403: enumerating which Socialite drivers happen to be
    // installed is not information a caller needs.
    $this->getJson('/api/v1/auth/sso/evilcorp/start')->assertNotFound();
});

test('the provider list is the allow-list, not whatever Socialite can resolve', function () {
    config([
        'auth.sso.providers' => 'google,microsoft',
        'services.microsoft' => ['client_id' => 'm-id', 'client_secret' => 'm-secret'],
    ]);

    $providers = $this->getJson('/api/v1/auth/sso/providers')->assertOk()->json('data.*.provider');

    expect($providers)->toBe(['google', 'microsoft']);
});

test('the whole surface disappears when the layer is disabled', function () {
    config(['auth.sso.enabled' => false]);

    $this->getJson('/api/v1/auth/sso/google/start')->assertNotFound();
});

test('a provider with no credentials is not offered at all', function () {
    // The env default names a provider as a setup hint, so this is the normal
    // state of a fresh install. Offering it renders a button that throws
    // DriverMissingConfigurationException the moment anyone presses it.
    config(['auth.sso.providers' => 'google', 'services.google' => []]);

    expect($this->getJson('/api/v1/auth/sso/providers')->assertOk()->json('data'))->toBe([]);
});

test('starting a listed-but-unconfigured provider says so rather than throwing', function () {
    config(['auth.sso.providers' => 'google', 'services.google' => []]);

    $this->getJson('/api/v1/auth/sso/google/start')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Sign-in with Google is not finished being set up.');
});

// ---------------------------------------------------------------------------
// start — driven against REAL Socialite. Building an authorization URL makes no
// network call, so nothing here needs a mock and the PKCE wiring is exercised
// for real rather than asserted against a stub that always agrees.
// ---------------------------------------------------------------------------

test('start sends PKCE and keeps the verifier server-side', function () {
    $url = $this->getJson('/api/v1/auth/sso/google/start')->assertOk()->json('url');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['code_challenge'] ?? null)->not->toBeEmpty()
        ->and($query['code_challenge_method'] ?? null)->toBe('S256')
        ->and($query['state'] ?? null)->not->toBeEmpty();

    // The verifier itself never leaves the server — it is held against the
    // state and replayed at exchange time. That binding is the whole point:
    // `start` is unauthenticated, so an attacker can mint a state of their own,
    // but they cannot mint the verifier the PROVIDER tied to someone else's
    // authorization code.
    $stored = Cache::get('sso:state:'.hash('sha256', $query['state']));

    expect($stored['verifier'] ?? '')->not->toBeEmpty()
        ->and($url)->not->toContain($stored['verifier']);
});

test('start does not hand the state back to the caller', function () {
    // Nothing client-side consumed it, and the client no longer needs one: the
    // handoff code is what it redeems. One less copy of a value whose only
    // purpose is to be hard to guess.
    $this->getJson('/api/v1/auth/sso/google/start')
        ->assertOk()
        ->assertJsonMissingPath('state');
});

// ---------------------------------------------------------------------------
// callback — state handling
// ---------------------------------------------------------------------------

test('a callback without state is refused', function () {
    fakeSocialite(ssoIdentity('1', 'ada@example.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc')
        ->assertRedirect()
        ->headers->get('Location');

    expect(handoffError($location))->not->toBeNull()
        ->and(handoffCode($location))->toBeNull();
});

test('a state cannot be replayed', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('1', 'ada@example.com'));

    $state = ssoState();

    $first = $this->get("/api/v1/auth/sso/google/callback?code=abc&state={$state}")->headers->get('Location');
    expect(handoffCode($first))->not->toBeNull();

    $second = $this->get("/api/v1/auth/sso/google/callback?code=abc&state={$state}")->headers->get('Location');
    expect(handoffCode($second))->toBeNull()
        ->and(handoffError($second))->not->toBeNull();
});

test('a state minted for one provider is not accepted by another', function () {
    config([
        'auth.sso.providers' => 'google,microsoft',
        'services.microsoft' => ['client_id' => 'm-id', 'client_secret' => 'm-secret'],
    ]);
    fakeSocialite(ssoIdentity('1', 'ada@example.com'));

    $state = ssoState('google');

    $location = $this->get("/api/v1/auth/sso/microsoft/callback?code=abc&state={$state}")->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

// ---------------------------------------------------------------------------
// The handoff: the callback must never render a credential
// ---------------------------------------------------------------------------

test('the callback redirects with a handoff code and never a token', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com'));

    $response = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->assertRedirect();

    $location = $response->headers->get('Location');

    // The regression this pins: the callback used to answer the provider's
    // top-level navigation with {"access_token": ...}. The browser rendered the
    // JSON, the SPA never saw it, and in a Capacitor build the token was left
    // sitting in a system browser the app cannot read.
    expect($response->getContent())->not->toContain('access_token')
        ->and($location)->toStartWith('https://app.test/auth/sso/complete')
        ->and(handoffCode($location))->not->toBeEmpty();
});

test('the handoff code exchanges for a token exactly once', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');
    $code     = handoffCode($location);

    $token = $this->postJson('/api/v1/auth/sso/exchange', ['code' => $code])
        ->assertOk()
        ->json('access_token');

    expect($token)->not->toBeEmpty()
        ->and(UserSsoIdentity::where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();

    // Single-use: a code left in browser history is spent.
    $this->postJson('/api/v1/auth/sso/exchange', ['code' => $code])->assertStatus(422);
});

test('an unknown handoff code is refused with the same message as a spent one', function () {
    // Distinguishing "expired", "already used" and "never existed" would say
    // whether a code was ever valid.
    $spent = $this->postJson('/api/v1/auth/sso/exchange', ['code' => str_repeat('a', 64)])
        ->assertStatus(422)
        ->json('message');

    expect($spent)->toBe('This sign-in has expired. Please try again.');
});

test('the return target is config-derived and cannot be steered by the caller', function () {
    // The classic SSO redirect bug: a caller-supplied return target turns the
    // sign-in endpoint into an open redirect that arrives holding a credential.
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com'));

    $location = $this->get(
        '/api/v1/auth/sso/google/callback?code=abc&state='.ssoState()
        .'&return_url=https://evil.test&RelayState=https://evil.test&next=https://evil.test'
    )->headers->get('Location');

    expect($location)->toStartWith('https://app.test/auth/sso/complete')
        ->and($location)->not->toContain('evil.test');
});

// ---------------------------------------------------------------------------
// Account linking
// ---------------------------------------------------------------------------

test('an unverified provider email cannot claim an existing account', function () {
    // The account takeover this prevents: set an unverified address at the
    // provider that matches a real user, sign in, inherit the account.
    User::factory()->create(['email' => 'victim@example.com']);
    fakeSocialite(ssoIdentity('attacker-1', 'victim@example.com', verified: false));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull()
        ->and(UserSsoIdentity::count())->toBe(0);
});

test('an unverified provider email cannot REGISTER either', function () {
    // The half that was missing. With registration on, an identity asserting an
    // unverified `cfo@client.com` used to get a local account bearing that
    // address, stamped email_verified_at = now() — which every later
    // invite-by-email or verified-user gate then treats as genuine, and which
    // the real owner is linked onto the moment they sign in.
    config(['auth.sso.allow_registration' => true]);
    fakeSocialite(ssoIdentity('attacker-1', 'cfo@client.test', verified: false));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull()
        ->and(User::where('email', 'cfo@client.test')->exists())->toBeFalse()
        ->and(UserSsoIdentity::count())->toBe(0);
});

test('a missing verification claim counts as unverified', function () {
    // Providers differ on the claim name, and a missing claim must not be read
    // as "fine" — that would make the check depend on the provider's vocabulary.
    User::factory()->create(['email' => 'victim@example.com']);

    $identity       = ssoIdentity('x', 'victim@example.com');
    $identity->user = [];
    fakeSocialite($identity);

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

test('a bare `verified` claim does not count as email verification', function () {
    // `verified` means something else on several providers — a blue check on X,
    // account standing on Facebook. Accepting it was a trapdoor waiting for
    // whoever added the next driver.
    User::factory()->create(['email' => 'victim@example.com']);

    $identity       = ssoIdentity('x', 'victim@example.com');
    $identity->user = ['verified' => true];
    fakeSocialite($identity);

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

test('a verified email links to the existing account', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->not->toBeNull()
        ->and(UserSsoIdentity::where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();
});

test('a second sign-in reuses the link rather than forking a duplicate', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com'));

    foreach ([ssoState(), ssoState()] as $state) {
        $this->get("/api/v1/auth/sso/google/callback?code=abc&state={$state}");
    }

    expect(UserSsoIdentity::count())->toBe(1);
});

test('the domain allow-list gates LINKING, not just registration', function () {
    // The dangerous half. On a multi-tenant provider an attacker can stand up
    // their own tenant, mint a user carrying a victim's address, mark it
    // verified in their own claim mapping, and be linked onto the victim's
    // account. Restricting which domains may arrive at all is the control a
    // project actually has; it previously applied to registration only.
    config(['auth.sso.allowed_domains' => 'company.com']);
    User::factory()->create(['email' => 'victim@example.com']);
    fakeSocialite(ssoIdentity('attacker-1', 'victim@example.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull()
        ->and(UserSsoIdentity::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Registration
// ---------------------------------------------------------------------------

test('an unknown email is refused while registration is off', function () {
    fakeSocialite(ssoIdentity('google-9', 'stranger@example.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull()
        ->and(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

test('registration creates an account when explicitly enabled', function () {
    config(['auth.sso.allow_registration' => true]);
    fakeSocialite(ssoIdentity('google-9', 'newbie@example.com', name: 'New Bie'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->not->toBeNull();

    $user = User::where('email', 'newbie@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('New')
        ->and($user->last_name)->toBe('Bie')
        // Never null: a null password makes "no password" and "not set yet"
        // indistinguishable, and some guards treat null as a match.
        ->and($user->password)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('registration still honours the domain allow-list', function () {
    config(['auth.sso.allow_registration' => true, 'auth.sso.allowed_domains' => 'company.com']);
    fakeSocialite(ssoIdentity('google-9', 'outsider@gmail.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull()
        ->and(User::where('email', 'outsider@gmail.com')->exists())->toBeFalse();
});

test('a failed registration leaves no half-built account behind', function () {
    // createUser and the identity row are one transaction. A user row without
    // its identity is an account nobody can reach: the link lookup misses it,
    // the email lookup finds it, and linking then demands proof the provider
    // may never send — so it exists, blocks registration, and can never be
    // signed into.
    config(['auth.sso.allow_registration' => true]);
    fakeSocialite(ssoIdentity('google-9', 'newbie@example.com'));

    $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState());

    $user = User::where('email', 'newbie@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(UserSsoIdentity::where('user_id', $user->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Refusals must not become an oracle
// ---------------------------------------------------------------------------

test('every identity refusal shows the same message regardless of cause', function () {
    // Three distinguishable refusals let an unauthenticated caller sort any
    // address into exists / does not exist / deactivated, one callback at a
    // time — at 10 requests a minute per IP, parallelised across IPs at will.
    // On a health or finance product, confirming that a named person holds an
    // account is the disclosure, before anyone gets near a takeover.
    User::factory()->create(['email' => 'known@example.com']);

    // (a) exists, but the provider will not vouch for the address
    fakeSocialite(ssoIdentity('a', 'known@example.com', verified: false));
    $exists = handoffError($this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location'));

    // (b) does not exist at all
    Mockery::close();
    fakeSocialite(ssoIdentity('b', 'unknown@example.com'));
    $missing = handoffError($this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location'));

    expect($exists)->not->toBeNull()
        ->and($missing)->not->toBeNull()
        // Same sentence, different reference. The reference is what a support
        // desk quotes back and what the log is grepped for.
        ->and(preg_replace('/reference \w+/', 'reference X', (string) $exists))
        ->toBe(preg_replace('/reference \w+/', 'reference X', (string) $missing));
});

test('the specific refusal reason is logged even though it is not shown', function () {
    Log::spy();

    fakeSocialite(ssoIdentity('google-9', 'stranger@example.com'));

    $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState());

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'stranger@example.com')
            && str_contains($message, 'registration is disabled'))
        ->once();
});

// ---------------------------------------------------------------------------
// Identity edge cases
// ---------------------------------------------------------------------------

test('an identity with no subject id is refused', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('', 'ada@example.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

test('an identity with no email is refused', function () {
    fakeSocialite(ssoIdentity('google-1', ''));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

test('email matching is case-insensitive, so casing does not fork an account', function () {
    config(['auth.sso.allow_registration' => true]);
    $user = User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ADA@Example.com'));

    $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState());

    expect(User::count())->toBe(1)
        ->and(UserSsoIdentity::where('user_id', $user->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Issuer pinning (C2) — which TENANT asserted the identity, not just which
// provider and which domain
// ---------------------------------------------------------------------------

test('no pin configured leaves the provider unrestricted', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['hd' => 'anything.test']));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->not->toBeNull();
});

test('an identity from the pinned tenant signs in', function () {
    config(['auth.sso.required_claims' => ['google' => ['hd' => 'example.com']]]);
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['hd' => 'example.com']));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->not->toBeNull();
});

test('nOAuth: an attacker tenant asserting OUR domain is refused', function () {
    // The whole point of the pin, and the case allowed_domains cannot catch.
    // Attacker owns `evil-tenant`, mints `ada@example.com` inside it, marks it
    // verified, and signs in through the same provider. Provider matches.
    // Domain matches — it is OUR domain, which is exactly why it is allowed.
    // Only the tenant claim tells the two identities apart.
    config([
        'auth.sso.required_claims' => ['google' => ['hd' => 'example.com']],
        'auth.sso.allowed_domains' => 'example.com',
    ]);
    $victim = User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('attacker-1', 'ada@example.com', claims: ['hd' => 'evil-tenant.test']));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull()
        ->and(UserSsoIdentity::where('user_id', $victim->id)->exists())->toBeFalse();
});

test('a pinned claim the provider does not send is refused, not waved through', function () {
    config(['auth.sso.required_claims' => ['google' => ['hd' => 'example.com']]]);
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com'));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

test('a pin whose expected value resolves empty refuses everything', function () {
    // An env var that did not resolve. The dangerous outcome is a pin that
    // reads as configured and enforces nothing, so this fails closed and loud.
    config(['auth.sso.required_claims' => ['google' => ['hd' => env('DEFINITELY_NOT_SET')]]]);
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['hd' => 'example.com']));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

test('a pin accepts a list of tenants', function () {
    config(['auth.sso.required_claims' => ['google' => ['hd' => ['a.test', 'example.com']]]]);
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['hd' => 'example.com']));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->not->toBeNull();
});

test('claim comparison is case-insensitive, so config casing is not a trapdoor', function () {
    config(['auth.sso.required_claims' => ['google' => ['tid' => 'A1B2C3D4-0000-0000-0000-000000000000']]]);
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['tid' => 'a1b2c3d4-0000-0000-0000-000000000000']));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->not->toBeNull();
});

test('adding a pin REVOKES an identity that was already linked under it', function () {
    // The reason the check sits above the link lookup. An operator adds a pin
    // because something got in that should not have; if the pin only applied
    // to first-time links, the one identity they are trying to remove is
    // precisely the one it would not touch.
    $user = User::factory()->create(['email' => 'ada@example.com']);
    UserSsoIdentity::create([
        'user_id'     => $user->id,
        'provider'    => 'google',
        'provider_id' => 'google-1',
        'email'       => 'ada@example.com',
    ]);

    config(['auth.sso.required_claims' => ['google' => ['hd' => 'example.com']]]);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['hd' => 'evil-tenant.test']));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});

test('a nested claim can be pinned with dot notation', function () {
    config(['auth.sso.required_claims' => ['google' => ['organization.id' => 'org-42']]]);
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['organization' => ['id' => 'org-42']]));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->not->toBeNull();
});

test('a non-scalar claim cannot be pinned and is refused rather than stringified', function () {
    config(['auth.sso.required_claims' => ['google' => ['groups' => 'admins']]]);
    User::factory()->create(['email' => 'ada@example.com']);
    fakeSocialite(ssoIdentity('google-1', 'ada@example.com', claims: ['groups' => ['admins', 'staff']]));

    $location = $this->get('/api/v1/auth/sso/google/callback?code=abc&state='.ssoState())->headers->get('Location');

    expect(handoffCode($location))->toBeNull();
});
