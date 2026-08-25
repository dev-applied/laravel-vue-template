<?php

declare(strict_types=1);

namespace Modules\Files\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Files\Models\File;

/** @mixin File */
class FileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'type'           => $this->type,
            'size'           => $this->size,
            'size_formatted' => $this->size_formatted,
            'processed'      => (bool) $this->processed,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
