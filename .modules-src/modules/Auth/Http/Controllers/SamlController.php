<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Auth\Models\UserSsoIdentity;
use Modules\Auth\Saml\SamlAssertion;
use Modules\Auth\Saml\SamlSettings;
use Modules\Auth\Sso\SsoIdentityResolver;
use Modules\Auth\Sso\SsoRefused;
use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\LogoutRequest;
use RuntimeException;
use Throwable;

/**
 * SAML 2.0 sign-in against an enterprise IdP (AD FS, Azure, Okta, PingFederate).
 *
 * Deliberately a SEPARATE controller from the OIDC one rather than another
 * Socialite driver: SAML is a different protocol, not a different provider. The
 * IdP POSTs a signed XML assertion to an endpoint of ours, where OIDC hands
 * back a code we redeem. What the two DO share is the part that matters — the
 * account-linking rules — so both end at the same SsoIdentityResolver, and the
 * same single-use handoff code carries the result to the app.
 *
 * The three bindings that keep an assertion from being replayable, since SAML
 * gives you none of them for free:
 *
 *   1. SIGNATURE — wantAssertionsSigned is forced on in SamlSettings. Everything
 *      else here is worthless without it.
 *   2. InResponseTo — an opaque token of ours rides in RelayState so the ACS can
 *      find which AuthnRequest this answers, and php-saml then requires the
 *      assertion's InResponseTo to match it cryptographically. RelayState is
 *      attacker-modifiable, so it is used ONLY as a cache key: substituting one
 *      selects a different expected request id and the match then fails. It is
 *      never, ever used as a redirect target — that is the classic SAML open
 *      redirect, and it arrives carrying a credential.
 *   3. REPLAY — the assertion id is remembered and refused a second time. The
 *      toolkit does not do this for you, and a signed assertion is otherwise
 *      valid for anyone holding a copy until its NotOnOrAfter passes.
 */
class SamlController extends Controller
{
    private const REQUEST_TTL = 600;

    private const REQUEST_PREFIX = 'saml:request:';

    private const ASSERTION_PREFIX = 'saml:assertion:';

    /**
     * How long a spent assertion id is remembered.
     *
     * Longer than any sane assertion lifetime on purpose: once NotOnOrAfter has
     * passed the toolkit rejects it on time grounds anyway, so this only has to
     * cover the window in which the assertion is still otherwise valid.
     */
    private const ASSERTION_TTL = 86400;

    private const HANDOFF_TTL = 120;

    private const HANDOFF_PREFIX = 'sso:handoff:';

    /**
     * SP metadata for the IdP administrator.
     *
     * Public and unauthenticated because it has to be: this describes us, and
     * the person configuring the other end needs it BEFORE they can give us the
     * entity id and certificate that `configured()` checks for. Gating it
     * behind a configured IdP would be a circular dependency.
     */
    public function metadata(): Response
    {
        if (! SamlSettings::enabled()) {
            return response('SAML is not enabled.', 404);
        }

        try {
            $settings = SamlSettings::buildForMetadata();
            $metadata = $settings->getSPMetadata();
            $errors   = $settings->validateMetadata($metadata);
        } catch (Throwable $e) {
            report($e);

            return response('SAML metadata could not be generated.', 500);
        }

        if ($errors !== []) {
            report(new RuntimeException('Invalid SP metadata: '.implode(', ', $errors)));

            return response('SAML metadata could not be generated.', 500);
        }

        return response($metadata, 200, ['Content-Type' => 'text/xml']);
    }

    /** Hand the client a URL to open. Mirrors the OIDC `start`. */
    public function start(Request $request): JsonResponse
    {
        if (! SamlSettings::configured()) {
            return $this->notConfigured();
        }

        // Our own opaque token, not the request id: the request id is chosen by
        // the toolkit and appears in the AuthnRequest, so it is not a secret.
        $relay = Str::random(40);

        try {
            $auth = new SamlAuth(SamlSettings::toArray());

            // $stay = true returns the URL instead of issuing a redirect and
            // calling exit() — this is an API endpoint, and the client opens
            // the URL itself.
            $url = $auth->login($relay, [], false, false, true);

            $requestId = $auth->getLastRequestID();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not start sign-in with '.SamlSettings::label().'.'], 500);
        }

        Cache::put(self::REQUEST_PREFIX.hash('sha256', $relay), [
            'request_id' => $requestId,
            'ip'         => $request->ip(),
        ], self::REQUEST_TTL);

        return response()->json(['url' => $url]);
    }

    /**
     * Assertion Consumer Service — where the IdP's browser POSTs the assertion.
     *
     * A cross-site form POST with no CSRF token, which is fine: these are `api`
     * routes and carry no session cookie, so there is no ambient authority to
     * abuse. The assertion's own signature is the authentication.
     */
    public function acs(Request $request, SsoIdentityResolver $resolver): RedirectResponse
    {
        if (! SamlSettings::configured()) {
            return $this->fail('Sign-in with '.SamlSettings::label().' is not available.');
        }

        $relay     = (string) $request->input('RelayState', '');
        $stored    = $relay === '' ? null : Cache::pull(self::REQUEST_PREFIX.hash('sha256', $relay));
        $requestId = $stored['request_id'] ?? null;

        // No matching request of ours. That is either a replayed RelayState or
        // an IdP-initiated sign-in — and an unsolicited assertion is precisely
        // the login-CSRF shape, so it is refused unless a project has opted in.
        if ($requestId === null && ! config('auth.saml.security.allow_idp_initiated', false)) {
            return $this->fail('This sign-in link has expired or was already used. Please try again.');
        }

        try {
            $auth = $this->process($request, $requestId);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Could not complete sign-in with '.SamlSettings::label().'.');
        }

        if (! $auth->isAuthenticated()) {
            // getErrors() is a list of codes; getLastErrorReason() explains the
            // signature or condition failure. Both to the log, neither to the
            // caller — they describe our trust configuration.
            Log::warning('[saml] assertion rejected', [
                'errors' => $auth->getErrors(),
                'reason' => $auth->getLastErrorReason(),
                'ip'     => $request->ip(),
            ]);

            return $this->fail('Could not complete sign-in with '.SamlSettings::label().'.');
        }

        if (! $this->claimAssertion($auth->getLastAssertionId())) {
            Log::warning('[saml] assertion replayed', [
                'assertion_id' => $auth->getLastAssertionId(),
                'ip'           => $request->ip(),
            ]);

            return $this->fail('This sign-in has already been used. Please try again.');
        }

        try {
            $user = $resolver->resolve(SamlSettings::PROVIDER, SamlAssertion::toIdentity($auth));
        } catch (SsoRefused $e) {
            Log::warning('[saml] refused ['.$e->reference.'] '.$e->getMessage(), [
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

        // Recorded now because it cannot be recovered later. A LogoutRequest we
        // send has to name the subject in the IdP's own terms, and a
        // LogoutRequest we RECEIVE identifies the subject by NameID and nothing
        // else — so without this, an IdP-initiated logout has no one to log out.
        $this->rememberSamlSession($user, $auth);

        $code = Str::random(64);

        // The same handoff the OIDC callback mints, redeemed at the same
        // /auth/sso/exchange endpoint — the app has one way to finish signing
        // in regardless of which protocol got it here.
        Cache::put(self::HANDOFF_PREFIX.hash('sha256', $code), [
            'user_id'  => $user->getKey(),
            'provider' => SamlSettings::PROVIDER,
        ], self::HANDOFF_TTL);

        return redirect()->away($this->returnUrl(['code' => $code]));
    }

    /**
     * Single Logout, both directions, on the one endpoint the SP metadata
     * already advertises.
     *
     * Two different messages arrive here and they are not symmetrical:
     *
     *  - `SAMLRequest` — the IdP telling us to end a session. This is the half
     *    that matters, and the half that was missing: `SamlSettings` builds a
     *    `singleLogoutService` entry whenever SAML_IDP_SLO_URL is set, so a
     *    correctly-configured Azure or Okta has been POSTing here all along and
     *    getting nothing. We revoke, then answer with a signed LogoutResponse.
     *  - `SAMLResponse` — the IdP answering a logout WE started. The local
     *    tokens are already gone by then (see `logout()`); this only closes the
     *    loop and returns the browser to the app.
     *
     * Unauthenticated by construction. The signature on the message is the
     * authentication — there is no session cookie on an api route to check, and
     * the request arrives as a top-level browser navigation from the IdP.
     */
    public function sls(Request $request): RedirectResponse
    {
        if (! SamlSettings::configured()) {
            return $this->fail('Sign-out with '.SamlSettings::label().' is not available.');
        }

        $revoked = 0;

        try {
            $redirect = $this->processSlo($request, function (SamlAuth $auth) use (&$revoked) {
                // Only ever called once the toolkit has validated the message,
                // which is the whole reason it is a callback rather than a step
                // before or after. Reading the NameID out of an UNVALIDATED
                // LogoutRequest and revoking on it would be an unauthenticated
                // denial of service: anyone could log anyone out by name.
                //
                // getLastRequestXML() is the document the toolkit just checked,
                // so the NameID is read from validated XML rather than decoded a
                // second time out of the raw query parameter.
                $revoked = $this->revokeForNameId((string) $auth->getLastRequestXML());
            });
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Could not complete sign-out with '.SamlSettings::label().'.');
        }

        if ($redirect === null) {
            return $this->fail('Could not complete sign-out with '.SamlSettings::label().'.');
        }

        Log::info('[saml] single logout processed', [
            'tokens_revoked' => $revoked,
            'ip'             => $request->ip(),
        ]);

        // A LogoutRequest gets a LogoutResponse URL back at the IdP; a
        // LogoutResponse ends the round trip, so the browser goes to the app.
        return redirect()->away($redirect === '' ? $this->returnUrl(['logout' => '1']) : $redirect);
    }

    /**
     * Start an SP-initiated logout: end the IdP session too, not just ours.
     *
     * Local tokens are revoked BEFORE the URL is handed back, never after the
     * round trip. The browser may never reach the IdP, the IdP may never come
     * back, the user may close the tab — and in every one of those cases the
     * only acceptable outcome is that they are logged out HERE. Ending the IdP
     * session is the bonus; ending ours is the requirement.
     *
     * Returns `url: null` rather than an error when there is nothing to do —
     * no SLO endpoint configured, or a user who signed in with a password. The
     * client logs out locally either way, so a missing URL is not a failure.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $identity = $user === null ? null : UserSsoIdentity::query()
            ->where('user_id', $user->getKey())
            ->where('provider', SamlSettings::PROVIDER)
            ->first();

        if ($user !== null) {
            $user->tokens()->delete();
        }

        if ($identity === null || ! SamlSettings::configured() || $identity->name_id === null) {
            return response()->json(['url' => null]);
        }

        if (mb_trim((string) config('auth.saml.idp.slo_url', '')) === '') {
            return response()->json(['url' => null]);
        }

        try {
            $auth = new SamlAuth(SamlSettings::toArray());

            $url = $auth->logout(
                returnTo: null,
                parameters: [],
                nameId: $identity->name_id,
                sessionIndex: $identity->session_index,
                stay: true,
                nameIdFormat: $identity->name_id_format,
            );
        } catch (Throwable $e) {
            report($e);

            // Local tokens are already gone, so this is a partial success, not
            // a failure: the user IS logged out of this app.
            return response()->json(['url' => null]);
        }

        $identity->forceFill(['session_index' => null])->saveQuietly();

        return response()->json(['url' => $url]);
    }

    /**
     * Store the three facts a LogoutRequest needs, straight off the assertion.
     */
    protected function rememberSamlSession(object $user, SamlAuth $auth): void
    {
        try {
            UserSsoIdentity::query()
                ->where('user_id', $user->getKey())
                ->where('provider', SamlSettings::PROVIDER)
                ->update([
                    'name_id'        => $auth->getNameId(),
                    'name_id_format' => $auth->getNameIdFormat(),
                    'session_index'  => $auth->getSessionIndex(),
                ]);
        } catch (Throwable $e) {
            // A sign-in that worked must not fail because logout bookkeeping
            // did. The cost of losing this is one SP-initiated logout that
            // cannot reach the IdP, which `logout()` already handles.
            report($e);
        }
    }

    /**
     * Revoke every token of whoever this LogoutRequest names.
     *
     * ALL tokens, not the one that happens to match a SessionIndex. Sanctum
     * issues a token per device and none of them carries an IdP session index,
     * so there is no mapping to be selective with — and "log this person out"
     * failing to log them out on a second device is the wrong way to be wrong.
     */
    protected function revokeForNameId(string $requestXml): int
    {
        if ($requestXml === '') {
            return 0;
        }

        // XML, not the deflated base64 off the wire: getNameIdData loads its
        // argument straight into a DOMDocument, so handing it the encoded form
        // produces a TypeError deep in the toolkit rather than a NameID.
        $nameId = LogoutRequest::getNameId($requestXml);

        if (! is_string($nameId) || $nameId === '') {
            return 0;
        }

        // name_id first, provider_id second: they are the same value until a
        // project points SAML_ATTR_SUBJECT somewhere else, and identities that
        // predate the SLO migration have only the latter.
        $identity = UserSsoIdentity::query()
            ->where('provider', SamlSettings::PROVIDER)
            ->where(fn ($q) => $q->where('name_id', $nameId)->orWhere('provider_id', $nameId))
            ->first();

        if ($identity === null || $identity->user === null) {
            // Not an error worth surfacing: a logout for someone who has no
            // local session is a no-op, and the IdP still gets its Success.
            return 0;
        }

        $count = (int) $identity->user->tokens()->count();

        $identity->user->tokens()->delete();
        $identity->forceFill(['session_index' => null])->saveQuietly();

        return $count;
    }

    /**
     * Run the toolkit over an SLO message.
     *
     * php-saml reads SLO strictly from `$_GET` — Single Logout is HTTP-Redirect
     * binding — and validates the redirect signature against the RAW query
     * string in `$_SERVER['QUERY_STRING']`, not against a re-encoding of the
     * parsed parameters. Those two differ in practice: re-encoding normalises
     * the percent-escapes and the byte sequence the IdP actually signed is
     * gone. Both superglobals are therefore populated from the Laravel request
     * and restored afterwards.
     *
     * Returns the IdP URL to send the browser to, '' when the message was a
     * LogoutResponse (nothing further to send), or null when it was invalid.
     */
    protected function processSlo(Request $request, callable $onValid): ?string
    {
        $previousGet   = $_GET;
        $previousQuery = $_SERVER['QUERY_STRING'] ?? null;

        $_GET = array_merge($_GET, array_filter([
            'SAMLRequest'  => $request->query('SAMLRequest'),
            'SAMLResponse' => $request->query('SAMLResponse'),
            'RelayState'   => $request->query('RelayState'),
            'SigAlg'       => $request->query('SigAlg'),
            'Signature'    => $request->query('Signature'),
        ], fn ($v) => $v !== null));

        // The RAW query string off the server bag, NOT $request->getQueryString().
        // Symfony's accessor runs normalizeQueryString, which ksorts the
        // parameters and re-encodes them RFC3986 (Request.php:679). The redirect
        // binding signs the query string as literal bytes in a fixed order, so
        // the normalised form is neither the right order nor the right bytes and
        // every signature check against it fails.
        $_SERVER['QUERY_STRING'] = (string) $request->server->get('QUERY_STRING', '');

        try {
            // Two attempts, because there is no single correct answer to how the
            // signed string is reconstructed and IdPs differ:
            //  - false: php-saml rebuilds it from the parsed parameters in SAML
            //    order with urlencode(). Right for most IdPs.
            //  - true:  php-saml uses the raw query string above verbatim. Right
            //    when the IdP encoded something differently from PHP — a literal
            //    '+' in base64, or RFC3986 vs application/x-www-form-urlencoded.
            // A failed attempt has no side effects: the callback only runs on the
            // valid branch, so retrying cannot revoke twice.
            foreach ([false, true] as $fromServer) {
                $auth = new SamlAuth(SamlSettings::forSlo());

                // keepLocalSession MUST be false or the callback never fires — the
                // toolkit reads the flag as "skip session teardown entirely", and
                // the callback IS the teardown. The default teardown it replaces is
                // Utils::deleteLocalSession(), which calls session_destroy() on
                // whatever PHP session happens to exist; letting that run inside a
                // Laravel request is not something to leave to chance.
                $redirect = $auth->processSLO(
                    keepLocalSession: false,
                    requestId: null,
                    retrieveParametersFromServer: $fromServer,
                    cbDeleteSession: fn () => $onValid($auth),
                    stay: true,
                );

                if ($auth->getErrors() === []) {
                    return is_string($redirect) ? $redirect : '';
                }

                $lastErrors = $auth->getErrors();
                $lastReason = $auth->getLastErrorReason();
            }

            Log::warning('[saml] SLO message rejected', [
                'errors' => $lastErrors ?? [],
                'reason' => $lastReason ?? null,
                'ip'     => $request->ip(),
            ]);

            return null;
        } finally {
            $_GET = $previousGet;

            if ($previousQuery === null) {
                unset($_SERVER['QUERY_STRING']);
            } else {
                $_SERVER['QUERY_STRING'] = $previousQuery;
            }
        }
    }

    /**
     * Run the toolkit over the POSTed assertion.
     *
     * php-saml reads $_POST directly, so it is populated from the Laravel
     * request and restored afterwards. Grubby, but the alternative is
     * reimplementing XML signature validation, and that is not a thing to
     * hand-roll.
     */
    protected function process(Request $request, ?string $requestId): SamlAuth
    {
        $previous = $_POST;

        $_POST = array_merge($_POST, array_filter([
            'SAMLResponse' => $request->input('SAMLResponse'),
            'RelayState'   => $request->input('RelayState'),
        ], fn ($v) => $v !== null));

        try {
            $auth = new SamlAuth(SamlSettings::toArray());
            $auth->processResponse($requestId);

            return $auth;
        } finally {
            $_POST = $previous;
        }
    }

    /**
     * Remember an assertion id, refusing a repeat.
     *
     * Cache::add is the atomic half of this: a plain has()-then-put() lets two
     * simultaneous replays both see "not seen yet" and both succeed, which is
     * the exact race a replay attack would create on purpose.
     */
    protected function claimAssertion(?string $assertionId): bool
    {
        // An assertion with no id cannot be tracked, so it cannot be trusted
        // not to be a replay.
        if ($assertionId === null || $assertionId === '') {
            return false;
        }

        return Cache::add(self::ASSERTION_PREFIX.hash('sha256', $assertionId), true, self::ASSERTION_TTL);
    }

    private function notConfigured(): JsonResponse
    {
        if (SamlSettings::enabled()) {
            report(new RuntimeException(
                'SAML is enabled but the IdP entity id / SSO URL / certificate are not all set.'
            ));

            return response()->json([
                'message' => 'Sign-in with '.SamlSettings::label().' is not finished being set up.',
            ], 503);
        }

        return response()->json(['message' => 'SAML sign-in is not enabled.'], 404);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->away($this->returnUrl(['error' => $message]));
    }

    /**
     * Config-derived, never from the request — and specifically never from
     * RelayState, which is the field an IdP round trip invites you to trust.
     *
     * @param  array<string, string>  $params
     */
    private function returnUrl(array $params): string
    {
        $base = mb_trim((string) config('auth.saml.return_url', ''))
            ?: mb_trim((string) config('auth.sso.return_url', ''))
            ?: url('/auth/sso/complete');

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($params);
    }
}
