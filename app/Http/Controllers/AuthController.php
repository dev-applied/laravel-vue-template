<?php

namespace App\Http\Controllers;

use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RateLimiter;
use Throwable;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'me', 'logout']]);
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        try {
            $user = Auth::user();
            $user->append('all_permissions');
        } catch (Throwable $exception) {
            $user = null;
        }

        return response()->json(compact('user'));
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param  Request  $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $this->validate($request, [
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        if (RateLimiter::tooManyAttempts('login:'.$request->ip(), 5)) {
            $seconds = RateLimiter::availableIn('login:'.$request->ip());

            return response()->json(['message' =>  'You may try again in ' . CarbonInterval::seconds($seconds)->cascade()->forHumans()], 401);
        }

        $user = User::where('email', $credentials['username'])->first();
        if (!$user) {
            RateLimiter::hit('login:'.$request->ip(), 60 * 5);
            return response()->json(['message' => 'User or password incorrect'], 401);
        }

        $token = Auth::attempt($credentials);

        if (!$token) {
            RateLimiter::hit('login:'.$request->ip(), 5 * 60);
            return response()->json(['message' => 'User or password incorrect'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json(['message' => 'Successfully logged out']);
    }


    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return JsonResponse
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        preg_match('/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/', config('app.url'), $matches);
        $response =  response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
//        $response->withCookie(cookie(name: 'token', value: $token, minutes: auth()->factory()->getTTL() * 60, domain: $matches[1]))
        return $response;
    }

}
