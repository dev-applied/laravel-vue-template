<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     * @throws Exception
     */
    public function send(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        if ($status != Password::RESET_LINK_SENT) {
            if ($status == Password::INVALID_USER) {
                //throw new Exception('User not found.'); //we don't want to give away if a user exists or not
            } else if ($status == Password::RESET_THROTTLED) {
                throw new Exception('Please wait before sending another reset email.');
            } else {
                throw new Exception('Error when trying to send reset email.');
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     * @throws Exception
     */
    public function reset(Request $request): JsonResponse
    {
        $this->validate($request, [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ]);

                $user->save();
            }
        );

        if ($status == Password::INVALID_USER) {
            throw new Exception('User not found.');
        } else if ($status == Password::INVALID_TOKEN) {
            throw new Exception('Invalid token.');
        } else if ($status == Password::RESET_THROTTLED) {
            throw new Exception('Please wait before resetting another password.');
        } elseif ($status !== Password::PASSWORD_RESET) {
            throw new Exception('Error when trying to reset password.');
        }

        $user = User::where('email', $request->input('email'))->firstOrFail();

        return response()->json(['success' => true, 'user' => $user]);
    }
}
