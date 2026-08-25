<?php

declare(strict_types=1);

namespace Modules\Support\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Models\TicketReply;

/** @mixin TicketReply */
class TicketReplyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'isInternal' => $this->is_internal,
            'author'     => $this->whenLoaded('author', fn (): ?string => $this->author
                ? mb_trim($this->author->first_name.' '.$this->author->last_name)
                : null),
            'createdAt' => $this->created_at,
        ];
    }
}
