<?php

declare(strict_types=1);

namespace Modules\Example\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Example\Models\ExampleNote;

/** @mixin ExampleNote */
class ExampleNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'note'       => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
