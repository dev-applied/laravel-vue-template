<?php

declare(strict_types=1);

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\SupportTicket;

/** @mixin SupportTicket */
class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'reference' => $this->reference,
            'name'      => $this->name,
            'email'     => $this->email,
            'subject'   => $this->subject,
            'body'      => $this->body,
            'status'    => $this->status,
            'priority'  => $this->priority,
            'isSpam'    => $this->is_spam,
            'assignee'  => $this->whenLoaded('assignee', fn (): ?array => $this->assignee ? [
                'id'   => $this->assignee->id,
                'name' => mb_trim($this->assignee->first_name.' '.$this->assignee->last_name),
            ] : null),
            'replies'    => TicketReplyResource::collection($this->whenLoaded('replies')),
            'resolvedAt' => $this->resolved_at,
            'createdAt'  => $this->created_at,
        ];
    }
}
