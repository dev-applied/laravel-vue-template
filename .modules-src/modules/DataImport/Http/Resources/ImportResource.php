<?php

declare(strict_types=1);

namespace Modules\DataImport\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DataImport\Models\DataImport;

/** @mixin DataImport */
class ImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'target'        => $this->target,
            'originalName'  => $this->original_name,
            'status'        => $this->status,
            'mapping'       => $this->mapping,
            'totalRows'     => $this->total_rows,
            'importedRows'  => $this->imported_rows,
            'failedRows'    => $this->failed_rows,
            'errors'        => $this->errors,
            'errorsTrimmed' => $this->failed_rows > count((array) $this->errors),
            'failureReason' => $this->failure_reason,
            'createdAt'     => $this->created_at,
            'completedAt'   => $this->completed_at,
        ];
    }
}
