<?php

declare(strict_types=1);

namespace Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tasks\Models\Task;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:10000'],
            'status'        => ['sometimes', Rule::in(Task::STATUSES)],
            'priority'      => ['sometimes', Rule::in(Task::PRIORITIES)],
            'assigned_to'   => ['nullable', 'integer', 'exists:users,id'],
            'due_at'        => ['nullable', 'date'],
            'taskable_type' => ['nullable', 'string', 'max:255', 'required_with:taskable_id'],
            'taskable_id'   => ['nullable', 'integer', 'required_with:taskable_type'],
        ];
    }
}
