<?php

declare(strict_types=1);

namespace Modules\Booking\Support;

use Illuminate\Support\Carbon;
use Modules\Booking\Models\BookableResource;
use Modules\Booking\Models\Booking;

/**
 * Turns a resource's weekly pattern into concrete bookable slots.
 *
 * Everything is stored UTC and computed in the resource's own timezone. The
 * opening hours are wall-clock — "we open at 9" means 9am local, which is a
 * different UTC instant either side of a DST change. Computing in UTC and
 * converting at the end shifts every slot by an hour twice a year.
 */
class AvailabilityCalculator
{
    /**
     * @return list<array{starts_at: string, ends_at: string, remaining: int}>
     */
    public function slots(BookableResource $resource, Carbon $from, Carbon $to): array
    {
        $tz = $resource->timezone;

        $windowStart = $from->copy()->utc();
        $windowEnd   = $to->copy()->utc();

        // A resource with no hours has no slots — not "always open", which is
        // what an empty-means-anything default would produce on the day
        // someone forgets to set them up.
        $patterns = $resource->availability()->get()->groupBy('day_of_week');

        if ($patterns->isEmpty()) {
            return [];
        }

        $blackouts = $resource->blackouts()
            ->where('starts_at', '<', $windowEnd)
            ->where('ends_at', '>', $windowStart)
            ->get();

        $held = $resource->bookings()
            ->overlapping($windowStart, $windowEnd)
            ->get(['starts_at', 'ends_at']);

        $earliest = now()->addMinutes($resource->min_notice_minutes);
        $latest   = now()->addDays($resource->advance_days);

        $slots  = [];
        $length = $resource->slot_minutes;
        $step   = $length + $resource->buffer_minutes;

        // Iterate DAYS in local time; a UTC day boundary is not a local one.
        $day     = $windowStart->copy()->setTimezone($tz)->startOfDay();
        $lastDay = $windowEnd->copy()->setTimezone($tz)->endOfDay();

        while ($day->lessThanOrEqualTo($lastDay)) {
            foreach ($patterns->get($day->dayOfWeek, collect()) as $pattern) {
                $open  = $this->at($day, $pattern->opens_at, $tz);
                $close = $this->at($day, $pattern->closes_at, $tz);

                // An overnight window (22:00–02:00) closes the next day.
                if ($close->lessThanOrEqualTo($open)) {
                    $close->addDay();
                }

                $cursor = $open->copy();

                while ($cursor->copy()->addMinutes($length)->lessThanOrEqualTo($close)) {
                    $start = $cursor->copy()->utc();
                    $end   = $cursor->copy()->addMinutes($length)->utc();

                    $cursor->addMinutes($step);

                    if ($start->lessThan($windowStart) || $end->greaterThan($windowEnd)) {
                        continue;
                    }

                    if ($start->lessThan($earliest) || $start->greaterThan($latest)) {
                        continue;
                    }

                    if ($this->isBlacked($blackouts, $start, $end)) {
                        continue;
                    }

                    $taken = $held->filter(
                        fn ($b) => $b->starts_at->lessThan($end) && $b->ends_at->greaterThan($start)
                    )->count();

                    $remaining = $resource->capacity - $taken;

                    if ($remaining <= 0) {
                        continue;
                    }

                    $slots[] = [
                        'starts_at' => $start->toIso8601String(),
                        'ends_at'   => $end->toIso8601String(),
                        'remaining' => $remaining,
                    ];
                }
            }

            $day->addDay();
        }

        usort($slots, fn ($a, $b) => $a['starts_at'] <=> $b['starts_at']);

        return $slots;
    }

    /**
     * True when this exact window is one the resource offers.
     *
     * Booking checks against the generated slots rather than trusting the
     * client's times — otherwise anyone can POST a 3am appointment on a closed
     * day and it is perfectly valid.
     */
    public function offers(BookableResource $resource, Carbon $start, Carbon $end): bool
    {
        foreach ($this->slots($resource, $start->copy()->subMinute(), $end->copy()->addMinute()) as $slot) {
            if (Carbon::parse($slot['starts_at'])->equalTo($start) && Carbon::parse($slot['ends_at'])->equalTo($end)) {
                return true;
            }
        }

        return false;
    }

    private function at(Carbon $day, string $time, string $tz): Carbon
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return $day->copy()->setTimezone($tz)->setTime((int) $h, (int) $m);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Modules\Booking\Models\ResourceBlackout>  $blackouts
     */
    private function isBlacked(mixed $blackouts, Carbon $start, Carbon $end): bool
    {
        foreach ($blackouts as $blackout) {
            if ($blackout->starts_at->lessThan($end) && $blackout->ends_at->greaterThan($start)) {
                return true;
            }
        }

        return false;
    }
}
