<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Item */
class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status?->value,
            'priority'    => $this->priority,
            'due_date'    => $this->due_date?->toIso8601String(),

            // whenLoaded() lets index lists skip the join cost while detail
            // views (which eager-load) still include the owner.
            'owner_id' => $this->owner_id,
            'owner'    => new AuthUserResource($this->whenLoaded('owner')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
