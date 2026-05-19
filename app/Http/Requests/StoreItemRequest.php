<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is auth:sanctum-gated; per-record permission can layer on top later.
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status'      => ['required', new Enum(ItemStatus::class)],
            'priority'    => ['required', 'integer', 'between:1,5'],
            'due_date'    => ['nullable', 'date'],
            'owner_id'    => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
