<?php

declare(strict_types=1);

namespace Modules\SavedViews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\SavedViews\Models\SavedView;

/**
 * @mixin SavedView
 */
class SavedViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id'        => $this->id,
            'key'       => $this->key,
            'name'      => $this->name,
            'payload'   => $this->payload,
            'isDefault' => $this->is_default,
            'isShared'  => $this->is_shared,
            'position'  => $this->position,
            // Drives whether the picker offers rename/delete at all, so the UI
            // never has to guess and then get a 403.
            'isOwn'     => $user !== null && $this->isEditableBy($user),
            'ownerName' => $this->when(
                $this->is_shared && $this->relationLoaded('user'),
                fn () => $this->user?->name ?? mb_trim(($this->user?->first_name ?? '').' '.($this->user?->last_name ?? '')) ?: null
            ),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
