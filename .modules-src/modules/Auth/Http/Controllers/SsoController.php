<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Modules\Auth\Saml\SamlSettings;
use Modules\Auth\Sso\SsoIdentity;
use Modules\Auth\Sso\SsoIdentityResolver;
use Modules\Auth\Sso\SsoProviders;
use Modules\Auth\Sso\SsoRefused;
use RuntimeException;
use Throwable;

/**
 * OIDC sign-in through Socialite.
 *
 * Three endpoints, and the shape matters:
 *
 *   start     — client asks for a provider URL and opens it.
 *   callback  — the PROVIDER's browser lands here. Redirects to the app with a
 *               single-use handoff code. Never renders a token.
 *   exchange  — the app redeems that code for a Sanctum token over a back
 *               channel it controls.
 *
 * The callback used to answer the browser with `{"access_token": ...}`. That
 * did not work and was not safe. It did not work because a provider redirect is
 * a top-level navigation, so the browser simply displayed the JSON — the SPA
 * never saw it, and there was no frontend handler anywhere. It was not safe
 * because in a Capacitor build that JSON renders in a SYSTEM browser which
 * shares no storage with the app: the app could not read the token even in
 * principle, and the token sat in a page in the user's real Chrome profile.
 *
 * API routes have no session, and neither does the system browser that
 * completes the round trip, so Socialite runs ->stateless(). That removes its
 * own CSRF handling, which is why this issues its own single-use `state` — and
 * why it also sends PKCE. State alone is not enough: `start` is unauthenticated
 * and hands out valid states to anyone, so an attacker holding an intercepted
 * authorization code could present it with a state they minted themselves and
 * have this server redeem it for them. PKCE is what actually closes that: the
 * verifier is bound by the PROVIDER to the authorization request that produced
 * the code, so a substituted state brings the wrong verifier and the exchange
 * fails at the provider.
 */
class SsoController extends Controller
{
    private const STATE_TTL = 600;

    private const STATE_PREFIX = 'sso:state:';

    /**
     * Deliberately short. The handoff code crosses a browser redirect, so it
     * lands in history; it is single-use and expires in two minutes, and it is
     * worth nothing without the POST that redeems it.
     */
    private const HANDOFF_TTL = 120;

    private const HANDOFF_PREFIX = 'sso:handoff:';

    public function start(Request $request, string $provider): JsonResponse
    {
        if (! $this->available($provider)) {
            return $this->unavailable($provider);
        }

        $state = Str::random(40);

        try {
            [$url, $verifier] = $this->authorizationUrl($provider, $state);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not start sign-in with '.SsoProviders::label($provider).'.'], 500);
        }

        Cache::put(self::STATE_PREFIX.hash('sha256', $state), [
            'provider' => $provider,
            'verifier' => $verifier,
            // Recorded, not trusted for authorization — useful when reading logs
            // after a failed sign-in.
            'ip' => $request->ip(),
        ], self::STATE_TTL);

        // The state is NOT returned. Nothing client-side consumed it, and the
        // client no longer needs it: the handoff code is what it redeems.
        return response()->json(['url' => $url]);
    }

    public function callback(Request $request, string $provider, SsoIdentityResolver $resolver): RedirectResponse
    {
        // Every exit from here is a redirect to an app-controlled URL. The
        // browser is sitting on this endpoint, so a JSON body would be shown to
        // a person.
        if (! $this->available($provider)) {
            return $this->fail('Sign-in with that provider is not available.');
        }

        $state = (string) $request->query('state', '');
        $key   = self::STATE_PREFIX.hash('sha256', $state);
        // Cache::pull, so a state is single-use: replaying a callback with a
        // code that already succeeded gets nothing.
        $stored = $state === '' ? null : Cache::pull($key);

        if ($stored === null || ($stored['provider'] ?? null) !== $provider) {
            return $this->fail('This sign-in link has expired or was already used. Please try again.');
        }

        try {
            $socialite = $this->exchangeCode($provider, (string) ($stored['verifier'] ?? ''));
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Could not complete sign-in with '.SsoProviders::label($provider).'.');
        }

        try {
            $user = $resolver->resolve($provider, SsoIdentity::fromSocialite($provider, $socialite));
        } catch (SsoRefused $e) {
            // The detail names which rule fired and stays in the log. The
            // caller gets one generic sentence and a reference, because the
            // three specific refusals this replaced let an unauthenticated
            // caller sort any address into exists / does not exist /
            // deactivated, one callback at a time.
            Log::warning('[sso] refused ['.$e->reference.'] '.$e->getMessage(), [
                'provider'  => $provider,
                'reference' => $e->reference,
                'ip'        => $request->ip(),
            ]);

            return $this->fail($e->publicMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Could not complete sign-in.');
        }

        if (method_exists($user, 'forceFill')) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        $code = Str::random(64);

        Cache::put(self::HANDOFF_PREFIX.hash('sha256', $code), [
            'user_id'  => $user->getKey(),
            'provider' => $provider,
        ], self::HANDOFF_TTL);

        // Query string rather than a fragment. A fragment never reaches a
        // server and would leak marginally less, but a Capacitor deep link is
        // the case that cannot be tested here and query parameters are what
        // every deep-link path preserves. What crosses the URL is a
        // two-minute single-use code that is worth nothing without the POST
        // below, not a credential.
        return redirect()->away($this->returnUrl(['code' => $code]));
    }

    /**
     * Redeem a handoff code for a Sanctum token.
     *
     * The back channel: called by the app itself, so the token is delivered
     * into the app's own storage rather than rendered into whatever browser the
     * provider happened to redirect.
     */
    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:128']]);

        $stored = Cache::pull(self::HANDOFF_PREFIX.hash('sha256', $validated['code']));

        // Single-use by Cache::pull, and one message for every failure mode —
        // expired, already spent, never existed. Distinguishing them would say
        // whether a code was ever valid.
        if ($stored === null) {
            return response()->json(['message' => 'This sign-in has expired. Please try again.'], 422);
        }

        $user = User::query()->find($stored['user_id'] ?? null);

        if ($user === null) {
            return response()->json(['message' => 'This sign-in has expired. Please try again.'], 422);
        }

        return response()->json([
            'access_token' => $user->createToken('sso:'.($stored['provider'] ?? 'sso'))->plainTextToken,
            'token_type'   => 'bearer',
        ]);
    }

    /**
     * Every sign-on option a login screen should offer, across BOTH protocols.
     *
     * Unauthenticated by design. `kind` tells the client which endpoint to
     * start — the two protocols begin differently even though they finish at
     * the same handoff — so the login screen renders one list and needs to know
     * nothing about which of them a project installed.
     */
    public function providers(): JsonResponse
    {
        $options = collect(SsoProviders::isEnabled() ? SsoProviders::enabled() : [])
            ->map(fn (string $p) => ['provider' => $p, 'label' => SsoProviders::label($p), 'kind' => 'oidc'])
            ->values();

        // class_exists because the `oidc` variant deletes Saml/** entirely —
        // referencing the class unguarded would fatal on a perfectly valid
        // install rather than simply offering nothing.
        if (class_exists(SamlSettings::class) && SamlSettings::configured()) {
            $options->push([
                'provider' => SamlSettings::PROVIDER,
                'label'    => SamlSettings::label(),
                'kind'     => 'saml',
            ]);
        }

        return response()->json(['data' => $options->values()]);
    }

    /**
     * Build the provider URL, and capture the PKCE verifier Socialite generated.
     *
     * Socialite stores the verifier in the session (AbstractProvider::redirect),
     * and these routes have none. Rather than reimplement PKCE and risk
     * disagreeing with Socialite about the challenge encoding, this hands it a
     * throwaway in-memory session, lets it generate and store the verifier as
     * usual, then reads it straight back out to keep in the cache alongside the
     * state.
     *
     * @return array{0: string, 1: string} the URL, and the verifier ('' when PKCE is off)
     */
    protected function authorizationUrl(string $provider, string $state): array
    {
        $usePkce = (bool) config('auth.sso.pkce', true);
        $session = $usePkce ? $this->scratchSession() : null;

        $driver = Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($this->callbackUrl($provider))
            ->with(['state' => $state]);

        if ($usePkce && method_exists($driver, 'enablePKCE')) {
            $driver->enablePKCE();
        }

        $url = $driver->redirect()->getTargetUrl();

        return [$url, $usePkce && $session ? (string) $session->get('code_verifier', '') : ''];
    }

    /**
     * Exchange the authorization code, replaying the stored verifier.
     *
     * Socialite pulls `code_verifier` out of the session when PKCE is on
     * (AbstractProvider::getTokenFields), so the verifier is put back into a
     * scratch session exactly where it expects to find it.
     */
    protected function exchangeCode(string $provider, string $verifier)
    {
        $driver = Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($this->callbackUrl($provider));

        if ($verifier !== '' && method_exists($driver, 'enablePKCE')) {
            $this->scratchSession()->put('code_verifier', $verifier);
            $driver->enablePKCE();
        }

        return $driver->user();
    }

    /**
     * An in-memory session attached to the current request, for Socialite's
     * benefit only. Never persisted, never sent as a cookie.
     */
    protected function scratchSession(): Store
    {
        $request = request();

        if (! $request->hasSession()) {
            $request->setLaravelSession(new Store('sso_pkce', new ArraySessionHandler(self::STATE_TTL)));
        }

        return $request->session();
    }

    private function available(string $provider): bool
    {
        return SsoProviders::isEnabled() && SsoProviders::allows($provider);
    }

    private function unavailable(string $provider): JsonResponse
    {
        // A provider named in config but missing credentials is a SETUP problem,
        // not a bad request, and saying so plainly is what a developer needs —
        // the alternative was a DriverMissingConfigurationException stack trace.
        // The raw flag, not isEnabled(): once the only listed provider is
        // unconfigured, enabled() is empty and isEnabled() is false — so
        // checking it here would send the very case this branch exists for
        // down to the 404 below.
        if (config('auth.sso.enabled', false) && in_array(mb_strtolower($provider), SsoProviders::listed(), true)) {
            report(new RuntimeException(
                "SSO provider [{$provider}] is listed in SSO_PROVIDERS but has no client_id/client_secret in config/services.php."
            ));

            return response()->json([
                'message' => 'Sign-in with '.SsoProviders::label($provider).' is not finished being set up.',
            ], 503);
        }

        // Otherwise 404, not 403: an unconfigured provider is not a thing this
        // app has, and enumerating which drivers exist helps nobody.
        return response()->json(['message' => "Unknown sign-in provider [{$provider}]."], 404);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->away($this->returnUrl(['error' => $message]));
    }

    /**
     * Always config-derived, never taken from the request.
     *
     * The classic redirect bug in every SSO integration is letting the caller
     * name where to land — a `RelayState`, a `?next=`, a `redirect_uri` echoed
     * back. That turns the sign-in endpoint into an open redirect that arrives
     * carrying a credential.
     *
     * @param  array<string, string>  $params
     */
    private function returnUrl(array $params): string
    {
        $base = mb_trim((string) config('auth.sso.return_url', '')) ?: url('/auth/sso/complete');

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($params);
    }

    private function callbackUrl(string $provider): string
    {
        return (string) (config('auth.sso.callback_url') ?: url("/api/v1/auth/sso/{$provider}/callback"));
    }
}
