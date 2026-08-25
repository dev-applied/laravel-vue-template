<?php

declare(strict_types=1);

namespace Modules\Tasks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Support\StatusMachine;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'dueAt'       => $this->due_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'isOverdue'   => $this->isOverdue(),
            'isClosed'    => $this->isClosed(),
            // The UI offers exactly these and nothing else, so a legal-looking
            // button can never produce a 422.
            'nextStatuses' => app(StatusMachine::class)->nextFrom($this->status),
            'position'     => $this->position,
            'assignee'     => $this->when($this->relationLoaded('assignee'), fn () => $this->assignee === null ? null : [
                'id'   => $this->assignee->getKey(),
                'name' => mb_trim(($this->assignee->first_name ?? '').' '.($this->assignee->last_name ?? ''))
                    ?: ($this->assignee->name ?? $this->assignee->email),
            ]),
            'taskableType' => $this->taskable_type,
            'taskableId'   => $this->taskable_id,
            'createdAt'    => $this->created_at?->toIso8601String(),
        ];
    }
}
