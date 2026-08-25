<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            // Optional. Omitting it creates the account without a usable
            // password and sends a set-password link, which is the safer
            // default: a password typed by an admin is a password that has
            // been typed into a chat window.
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        // An untouched password field posts as "" rather than being absent.
        // `nullable` does not catch that — "" is present and fails min-length —
        // so a form that only edits a name would be rejected for a password the
        // user never typed. Normalise here rather than in the client, so every
        // caller (web, mobile, API) behaves the same.
        if ($this->input('password') === '') {
            $this->request->remove('password');
            $this->request->remove('password_confirmation');
        }

        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(mb_trim((string) $this->input('email')))]);
        }
    }
}
