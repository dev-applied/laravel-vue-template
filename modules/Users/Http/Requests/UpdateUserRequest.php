<?php

declare(strict_types=1);

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'string', 'max:255'],
            'email'      => [
                'sometimes', 'email', 'max:255',
                // Ignoring self, or saving a user without changing their email
                // fails validation against their own row.
                Rule::unique('users', 'email')->ignore($user?->getKey()),
            ],
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
