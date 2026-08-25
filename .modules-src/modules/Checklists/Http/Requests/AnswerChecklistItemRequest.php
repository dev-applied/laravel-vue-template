<?php

declare(strict_types=1);

namespace Modules\Checklists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Checklists\Models\ChecklistResponse;

class AnswerChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `pending` is not an accepted answer. It is the ABSENCE of one, and
            // letting a caller set it back would quietly un-answer a line
            // somebody had already signed off.
            'status'  => ['required', 'string', Rule::in(ChecklistResponse::ANSWERS)],
            'note'    => ['nullable', 'string', 'max:2000'],
            'file_id' => ['nullable', 'integer'],
        ];
    }
}
