<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Booking\Models\Booking;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference'   => $this->reference,
            'name'        => $this->name,
            'email'       => $this->email,
            'notes'       => $this->notes,
            'status'      => $this->status,
            'startsAt'    => $this->starts_at?->toIso8601String(),
            'endsAt'      => $this->ends_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'resource'    => $this->when($this->relationLoaded('resource'), fn () => [
                'name'     => $this->resource?->name,
                'slug'     => $this->resource?->slug,
                'timezone' => $this->resource?->timezone,
            ]),
        ];
    }
}
