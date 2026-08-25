<?php

declare(strict_types=1);

namespace Modules\Tasks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
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
            // Drives whether the UI offers edit/delete, so it never offers and
            // then 403s — the same contract CommentResource::canEdit has, and
            // the module's own README states it as a rule: a visible button can
            // never produce an error.
            'canEdit'   => $this->mayEdit($request),
            'canDelete' => $this->mayDelete($request),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Creator, assignee, or the override. Mirrors TaskController::assertMayEdit. */
    private function mayEdit(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $this->isCreator($user)
            || (int) $this->assigned_to === (int) $user->getKey()
            || Gate::forUser($user)->allows('manage-tasks');
    }

    /**
     * Stricter than editing: the assignee does not qualify. Being given a job
     * is not permission to destroy the record of it.
     */
    private function mayDelete(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $this->isCreator($user) || Gate::forUser($user)->allows('manage-tasks');
    }

    private function isCreator(mixed $user): bool
    {
        $column = $this->resource->getCreatedByColumn();

        return $this->resource->getAttribute($column) !== null
            && (int) $this->resource->getAttribute($column) === (int) $user->getKey();
    }
}
