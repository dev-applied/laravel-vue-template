<?php

declare(strict_types=1);

namespace Modules\Announcements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Announcements\Models\Announcement;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is gated by the `manage-announcements` ability. A project
        // without a Gate definition denies by default, which is the safe way
        // round for a surface that broadcasts to every user.
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                    => ['required', 'string', 'max:255'],
            'body'                     => ['required', 'string', 'max:5000'],
            'level'                    => ['sometimes', Rule::in(Announcement::LEVELS)],
            'placement'                => ['sometimes', Rule::in(Announcement::PLACEMENTS)],
            'audience'                 => ['sometimes', 'string', 'max:255'],
            'dismissible'              => ['sometimes', 'boolean'],
            'requires_acknowledgement' => ['sometimes', 'boolean'],
            'action_label'             => ['nullable', 'string', 'max:64', 'required_with:action_url'],
            'action_url'               => ['nullable', 'string', 'max:2048', 'required_with:action_label'],
            'starts_at'                => ['nullable', 'date'],
            // An announcement whose window closes before it opens is never
            // shown to anyone, and nothing about the UI would tell you why.
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
