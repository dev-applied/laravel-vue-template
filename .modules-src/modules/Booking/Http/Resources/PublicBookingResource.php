<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Booking\Models\Booking;

/**
 * What the reference lookup returns — deliberately less than the admin one.
 *
 * `GET /booking/reference/{reference}` is unauthenticated: the reference IS the
 * credential. It was returning the same {@see BookingResource} the staff listing
 * uses, so a reference that leaked — a forwarded confirmation, a screenshot, a
 * quote in a support thread — leaked the booker's email address and their free-text
 * `notes` along with it. On a clinic or a legal booking resource, `notes` is the
 * sensitive field in the row.
 *
 * Everything here is something the person holding the reference already knows,
 * so nothing is lost by scoping it to the confirmation page's actual needs: what
 * was booked, when, and whether it still stands.
 */
class PublicBookingResource extends JsonResource
{
    /** @var Booking */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference'   => $this->resource->reference,
            'status'      => $this->resource->status,
            'startsAt'    => $this->resource->starts_at?->toIso8601String(),
            'endsAt'      => $this->resource->ends_at?->toIso8601String(),
            'cancelledAt' => $this->resource->cancelled_at?->toIso8601String(),
            'resource'    => $this->when($this->resource->relationLoaded('resource'), fn () => [
                'name'     => $this->resource->resource?->name,
                'slug'     => $this->resource->resource?->slug,
                'timezone' => $this->resource->resource?->timezone,
            ]),
        ];
    }
}
