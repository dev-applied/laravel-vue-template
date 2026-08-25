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
    /**
     * Named explicitly because Booking has a relation called `resource`, which
     * collides with JsonResource's OWN $resource property — the wrapped model.
     *
     * Every other field here reaches the model through the @mixin magic
     * ($this->reference -> $booking->reference), so `$this->resource->name`
     * reads like "the related resource's name" and is not: $this->resource IS
     * the Booking, so it returned the BOOKER'S name, with null slug and null
     * timezone because a booking has no such columns. The public confirmation
     * page rendered "Where: Dana Visitor".
     *
     * Declaring the type makes the model explicit, and the relation is reached
     * by going through it: $this->resource->resource.
     *
     * @var Booking
     */
    public $resource;

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
            'resource'    => $this->when($this->resource->relationLoaded('resource'), fn () => [
                'name'     => $this->resource->resource?->name,
                'slug'     => $this->resource->resource?->slug,
                'timezone' => $this->resource->resource?->timezone,
            ]),
        ];
    }
}
