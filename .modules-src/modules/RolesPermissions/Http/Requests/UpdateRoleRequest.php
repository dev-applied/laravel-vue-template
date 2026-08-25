<?php

declare(strict_types=1);

namespace Modules\RolesPermissions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'string', 'max:125',
                Rule::unique('roles', 'name')->ignore($this->route('role')?->id),
            ],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }
}
