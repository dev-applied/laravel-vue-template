<?php

declare(strict_types=1);

namespace Modules\Invitations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Deliberately NOT unique:users — see InvitationController::store.
            'email' => ['required', 'email', 'max:255'],
            'role'  => ['sometimes', 'nullable', 'string', 'max:125', ...$this->roleRules()],
        ];
    }

    /**
     * Two separate controls on `role`, because it was previously a free string
     * that `AcceptInvitationController` passed straight to `assignRole()`.
     *
     * 1. It must be a role that EXISTS. Without the roles table there is no
     *    spatie install, so `assignRole` does not exist and the value is inert
     *    today — but it is stored, and it becomes live the day the project adds
     *    the module. A stored role nobody validated is a landmine, so it is
     *    refused outright rather than kept.
     *
     * 2. The inviter must already HOLD the role they are handing out, unless
     *    they pass `assign-any-role`. Otherwise "may invite people" silently
     *    means "may create a super-admin", and the gate on the route is not the
     *    boundary it appears to be — an invitation manager could promote
     *    themselves through a second account.
     *
     * @return array<int, mixed>
     */
    protected function roleRules(): array
    {
        if (! Schema::hasTable('roles')) {
            return ['prohibited'];
        }

        return [
            Rule::exists('roles', 'name'),
            function (string $attribute, mixed $value, callable $fail): void {
                if (blank($value) || Gate::allows('assign-any-role')) {
                    return;
                }

                $user = $this->user();

                if ($user === null || ! method_exists($user, 'hasRole') || ! $user->hasRole($value)) {
                    $fail('You can only invite someone to a role you hold yourself.');
                }
            },
        ];
    }
}
