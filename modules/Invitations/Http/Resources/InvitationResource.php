<?php

declare(strict_types=1);

namespace Modules\Invitations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invitations\Models\Invitation;

/** @mixin Invitation */
class InvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'email'     => $this->email,
            'role'      => $this->role,
            'status'    => $this->status(),
            'invitedBy' => $this->whenLoaded('inviter', fn (): ?string => $this->inviter
                ? mb_trim($this->inviter->first_name.' '.$this->inviter->last_name)
                : null),
            'expiresAt'  => $this->expires_at,
            'acceptedAt' => $this->accepted_at,
            'createdAt'  => $this->created_at,
        ];
    }
}
