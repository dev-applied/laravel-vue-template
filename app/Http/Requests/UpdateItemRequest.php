<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 'sometimes' lets PATCH/PUT clients send partial bodies. The shape mirrors
        // StoreItemRequest so frontends can reuse the same validation contract.
        return [
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status'      => ['sometimes', 'required', new Enum(ItemStatus::class)],
            'priority'    => ['sometimes', 'required', 'integer', 'between:1,5'],
            'due_date'    => ['sometimes', 'nullable', 'date'],
            'owner_id'    => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
