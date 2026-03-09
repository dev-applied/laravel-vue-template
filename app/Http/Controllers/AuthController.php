<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new AuthUserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

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
