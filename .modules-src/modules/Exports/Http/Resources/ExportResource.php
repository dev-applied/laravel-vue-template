<?php

declare(strict_types=1);

namespace Modules\Exports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Exports\Models\Export;

/** @mixin Export */
class ExportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'source'       => $this->source,
            'format'       => $this->format,
            'status'       => $this->status,
            'rowCount'     => $this->row_count,
            'error'        => $this->error,
            'downloadable' => $this->isDownloadable(),
            'fileName'     => $this->fileName(),
            'createdAt'    => $this->created_at,
            'completedAt'  => $this->completed_at,
        ];
    }
}
