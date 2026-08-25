<?php

declare(strict_types=1);

namespace Modules\Invitations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token'      => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:125'],
            'last_name'  => ['required', 'string', 'max:125'],
            'password'   => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
