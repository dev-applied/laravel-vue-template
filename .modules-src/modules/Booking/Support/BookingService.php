<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

use App\Exceptions\AppException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Models\BookableResource;
use Modules\Booking\Models\Booking;

class BookingService
{
    public function __construct(private readonly AvailabilityCalculator $availability) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function book(BookableResource $resource, Carbon $start, Carbon $end, array $attributes): Booking
    {
        if (! $resource->is_active) {
            throw new AppException('That resource is not taking bookings.', 422);
        }

        // Never trust the client's window. Without this anyone can POST a 3am
        // appointment on a closed day and it is perfectly valid.
        if (! $this->availability->offers($resource, $start, $end)) {
            throw new AppException('That time is not available.', 422);
        }

        return DB::transaction(function () use ($resource, $start, $end, $attributes) {
            // The whole reason this is a transaction. Two people hitting the
            // last slot within milliseconds both pass the availability check;
            // only the row lock makes the second one lose. Checking without
            // locking is the double-booking bug that is in nearly every
            // hand-rolled version of this.
            $taken = Booking::query()
                ->where('bookable_resource_id', $resource->getKey())
                ->overlapping($start, $end)
                ->lockForUpdate()
                ->count();

            if ($taken >= $resource->capacity) {
                throw new AppException('That time was just taken.', 409);
            }

            return Booking::create([
                ...$attributes,
                'bookable_resource_id' => $resource->getKey(),
                'starts_at'            => $start,
                'ends_at'              => $end,
                'status'               => config('booking.requires_approval', false)
                    ? Booking::STATUS_PENDING
                    : Booking::STATUS_CONFIRMED,
            ]);
        });
    }

    public function cancel(Booking $booking): Booking
    {
        if ($booking->isCancelled()) {
            // Idempotent: a double-click on Cancel must not be an error.
            return $booking;
        }

        $booking->update([
            'status'       => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $booking->fresh();
    }

    public function approve(Booking $booking): Booking
    {
        if ($booking->isCancelled()) {
            throw new AppException('A cancelled booking cannot be approved.', 422);
        }

        // The same transaction + row lock `book()` uses, for the same reason.
        // This method's capacity re-check existed all along; it was a bare
        // count() followed by an update(), which is the check-then-act it was
        // written to prevent. An admin working a pending queue approves two
        // requests for one capacity-1 slot in the same second — two tabs, or
        // just a fast list — and both count zero confirmed, both pass, and two
        // people are told to show up.
        return DB::transaction(function () use ($booking) {
            $taken = Booking::query()
                ->where('bookable_resource_id', $booking->bookable_resource_id)
                ->whereKeyNot($booking->getKey())
                ->where('status', Booking::STATUS_CONFIRMED)
                ->overlapping($booking->starts_at, $booking->ends_at)
                ->lockForUpdate()
                ->count();

            if ($taken >= $booking->resource->capacity) {
                throw new AppException('That slot filled up while this request was pending.', 409);
            }

            // Conditional update, so two approvals of the SAME booking cannot
            // both believe they did it. The lock above orders them; this makes
            // the loser observable rather than silent.
            $confirmed = Booking::query()
                ->whereKey($booking->getKey())
                ->where('status', Booking::STATUS_PENDING)
                ->update(['status' => Booking::STATUS_CONFIRMED]);

            if ($confirmed === 0 && $booking->fresh()?->status !== Booking::STATUS_CONFIRMED) {
                throw new AppException('That booking is no longer pending approval.', 409);
            }

            return $booking->fresh();
        });
    }
}
