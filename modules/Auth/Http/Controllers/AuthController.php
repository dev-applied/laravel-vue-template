<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $key = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($key);

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('auth-token');

        // With the OAuth layer on, also open a web session so this same browser
        // can approve /oauth/authorize without logging in a second time. The
        // route only carries session middleware when oauth is enabled.
        if (config('auth.oauth.enabled', false) && $request->hasSession()) {
            Auth::guard('web')->login($user, true);
            $request->session()->regenerate();
        }

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        // Resolve via the sanctum guard explicitly: this route is deliberately
        // outside auth:sanctum middleware (it must answer {user: null} for
        // guests), and the default guard is web — $request->user() would
        // ignore bearer tokens entirely, so mobile/token sessions read as
        // logged out even with a valid token attached.
        $user = $request->user('sanctum');

        // Whether THIS session is an impersonation, so the client can say so.
        //
        // impersonate() mints a token carrying the `impersonated` ability, and
        // until now nothing told the browser about it — so the kernel shipped
        // an AppImpersonationBanner component that no page could ever decide to
        // show. An impersonating session that looks identical to a real one is
        // the whole hazard: somebody forgets, and then acts as that user
        // believing they are themselves.
        $token = $user?->currentAccessToken();

        return response()->json([
            'user' => $user ? new AuthUserResource($user) : null,
            // in_array, NOT $token->can(). Sanctum's can() answers true for any
            // ability when the token holds the `*` wildcard — which every
            // ordinary login token does — so can('impersonated') reports every
            // normal session as an impersonation. The ability has to be
            // explicitly present.
            'impersonating' => $token !== null
                && in_array('impersonated', (array) ($token->abilities ?? []), true),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')?->currentAccessToken()?->delete();

        if (config('auth.oauth.enabled', false) && $request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Mint a token for another user.
     *
     * Gated by `impersonate-users` on the route, which denies by default — see
     * ModuleServiceProvider::registerImpersonationGate(). Do not remove it: this
     * endpoint's entire job is to hand back someone else's credentials.
     */
    public function impersonate(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $actor  = $request->user();
        $target = User::findOrFail($request->user_id);

        // Not an error worth a stack trace, but not worth minting a token for
        // either — it would be a second, longer-lived token for the session that
        // already exists, and "stop impersonating" would then log them out.
        if ($actor !== null && $actor->getKey() === $target->getKey()) {
            return response()->json(['message' => 'You are already signed in as that user.'], 422);
        }

        // Always logged, at info rather than debug. Impersonation is one account
        // acting as another; if it is ever questioned afterwards, this line is
        // the only record that it happened and who did it.
        Log::info('[auth] impersonation started', [
            'actor_id'  => $actor?->getKey(),
            'target_id' => $target->getKey(),
            'ip'        => $request->ip(),
        ]);

        $token = $target->createToken(
            'impersonation-token',
            ['impersonated'],
            now()->addMinutes((int) config('auth.impersonation.ttl_minutes', 60)),
        );

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'bearer',
        ]);
    }

    public function stopImpersonating(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Impersonation stopped']);
    }
}
