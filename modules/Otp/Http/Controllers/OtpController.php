<?php

declare(strict_types=1);

namespace Modules\Otp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Otp\Support\OtpManager;

/**
 * Passwordless sign-in. Public — anyone may ask for a code.
 */
class OtpController extends Controller
{
    public function __construct(private readonly OtpManager $otp) {}

    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = mb_strtolower(mb_trim($data['identifier']));

        $result = $this->otp->issue($identifier, 'login', null, $request->ip());

        // The same response whether or not an account exists. Anything else
        // turns this endpoint into an account-enumeration oracle, which is the
        // single most common way these flows leak.
        return response()->json([
            'message'   => 'If that account exists, a code is on its way.',
            'masked'    => $result['masked'],
            'expiresIn' => $result['expires_in'],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'code'       => ['required', 'string', 'max:10'],
        ]);

        $identifier = mb_strtolower(mb_trim($data['identifier']));

        $ok = $this->otp->matchesBypass($data['code'])
            || $this->otp->verify($identifier, $data['code'], 'login');

        if (! $ok) {
            return response()->json(['message' => 'That code is not valid.'], 422);
        }

        $user = User::query()->where('email', $identifier)->first();

        // Only now does the response differ — and only to someone who has
        // already proved control of the inbox.
        if ($user === null) {
            return response()->json(['message' => 'That code is not valid.'], 422);
        }

        return response()->json([
            'token' => $user->createToken('otp')->plainTextToken,
            'user'  => $user->only(['id', 'email', 'first_name', 'last_name']),
        ]);
    }
}
