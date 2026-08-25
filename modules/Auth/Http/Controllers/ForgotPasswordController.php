<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function send(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        if ($status !== Password::RESET_LINK_SENT) {
            if ($status === Password::INVALID_USER) {
                // throw new Exception('User not found.'); //we don't want to give away if a user exists or not
            } else if ($status === Password::RESET_THROTTLED) {
                throw new Exception('Please wait before sending another reset email.');
            } else {
                throw new Exception('Error when trying to send reset email.');
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function reset(Request $request): JsonResponse
    {
        $this->validate($request, [
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                ]);

                $user->save();
            }
        );

        // One refusal for every way this can fail.
        //
        // sendResetLink() above already declines to say whether an address is
        // registered — there is a comment there saying so — and this method
        // then undid it: 'User not found.' versus 'Invalid token.' separates
        // "no such account" from "bad token" for anyone posting a guessed token
        // with an address they want to test. The real reason is logged with a
        // reference, so support can still answer "why did mine fail".
        if ($status !== Password::PASSWORD_RESET) {
            $reference = (string) Str::uuid();

            Log::warning('auth: password reset refused', [
                'reference' => $reference,
                'status'    => $status,
            ]);

            throw new Exception(
                "That reset link is no longer valid. Request a new one. (Reference: {$reference})"
            );
        }

        $user = User::where('email', $request->input('email'))->firstOrFail();

        // An allow-listed resource, not the model. `compact('user')` shipped
        // every column on the users table, so any module or migration that
        // added one — Users added deactivated_at — silently started returning
        // it here. The kernel's AuthUserResource is the same allow-list /auth
        // uses. Nothing in the frontend reads this key at all; it is kept only
        // so a project reading it does not break.
        return response()->json(['user' => new AuthUserResource($user)]);
    }
}
