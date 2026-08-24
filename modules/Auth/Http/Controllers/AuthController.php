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

        return response()->json([
            'user' => $user ? new AuthUserResource($user) : null,
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

    public function impersonate(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $target = User::findOrFail($request->user_id);
        $token  = $target->createToken('impersonation-token', ['impersonated']);

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
