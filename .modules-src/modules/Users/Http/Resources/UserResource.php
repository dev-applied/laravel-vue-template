<?php

declare(strict_types=1);

namespace Modules\Users\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'firstName'     => $this->first_name,
            'lastName'      => $this->last_name,
            'name'          => mb_trim($this->first_name.' '.$this->last_name),
            'email'         => $this->email,
            'emailVerified' => $this->email_verified_at !== null,
            'isActive'      => $this->deactivated_at === null,
            'deactivatedAt' => $this->iso($this->deactivated_at),
            'lastLoginAt'   => $this->iso($this->last_login_at),
            'createdAt'     => $this->iso($this->created_at),
            // So the UI hides destructive controls on the viewer's own row
            // rather than offering them and then 422ing.
            'isSelf' => $request->user()?->getKey() === $this->id,
        ];
    }

    /**
     * `deactivated_at` is a column this MODULE added to the kernel's users
     * table, so it is absent from the kernel User model's $casts and arrives
     * as a raw string. Calling a Carbon method on it is a 500 on the listing.
     * Parsed here rather than requiring the kernel model to be edited.
     */
    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value->toIso8601String() : Carbon::parse((string) $value)->toIso8601String();
    }
}
