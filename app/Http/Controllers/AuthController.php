<?php

namespace App\Http\Controllers;

use App\Exceptions\AppException;
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
            $user->append('is_impersonated');
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

        $user = User::where('email', $credentials['email'])->first();
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
     * @throws ValidationException
     */
    public function impersonate(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'user_id' => 'required|exists:users,id'
        ]);

        /** @var User $user */
        $user = User::findOrFail($data['user_id']);

        $token = Auth::user()->impersonate($user);

        return $this->respondWithToken($token);
    }

    /**
     * @throws AppException
     */
    public function stopImpersonating(): JsonResponse
    {
        $token = Auth::user()->leaveImpersonation();

        return $this->respondWithToken($token);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out'])->withoutCookie('token', null, $this->getRootDomain());
    }

    private function getRootDomain(): string
    {
        preg_match('/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/', config('app.url'), $matches);
        return $matches[1];
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
        $response = response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
        $response->withCookie(cookie(name: 'token', value: $token, minutes: auth()->factory()->getTTL() * 60, domain: $this->getRootDomain()));
        return $response;
    }

}
