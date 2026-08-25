<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AuditLog\Models\AuditLog;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'event'   => $this->event,
            'subject' => [
                'type' => class_basename($this->auditable_type),
                'id'   => $this->auditable_id,
            ],
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user ? [
                'id'   => $this->user->id,
                'name' => mb_trim($this->user->first_name.' '.$this->user->last_name),
            ] : null),
            'changes'   => $this->changeSet(),
            'ipAddress' => $this->ip_address,
            'createdAt' => $this->created_at,
        ];
    }

    /**
     * Pair old and new per field so the UI renders a diff without having to
     * reconcile two loosely related maps itself.
     *
     * @return array<int, array<string, mixed>>
     */
    private function changeSet(): array
    {
        return array_map(fn (string $field): array => [
            'field' => $field,
            'from'  => $this->old_values[$field] ?? null,
            'to'    => $this->new_values[$field] ?? null,
        ], $this->changedFields());
    }
}
