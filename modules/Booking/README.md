# Booking

Slot-based booking for any resource: weekly availability, one-off blackouts,
capacity, notice windows, and creation that cannot double-book.

Appointment and resource scheduling recurs, and the double-booking race is
present in nearly every hand-rolled version — the check runs, then the insert
runs, and two people who clicked within the same second both pass.

## What it gives you

| Piece | What it does |
|---|---|
| `bookable_resources` | Slot length, buffer, capacity, notice, advance window, timezone. |
| `resource_availability` | The weekly pattern, in the resource's local time. |
| `resource_blackouts` | One-off closures — holidays, maintenance. |
| `AvailabilityCalculator` | Turns the pattern into concrete free slots. |
| `BookingService` | Overlap-safe creation under a row lock. |
| `BookingPage` | Public slot picker and booking form. |

## Install

```sh
php artisan module:add Booking
php artisan migrate
```

**Option — `approval`:**

| Choice | What you get |
|---|---|
| `instant` (default) | A booked slot is confirmed and held immediately. |
| `request` | Bookings arrive `pending` and someone approves them. |

```php
Gate::define('manage-bookings', fn ($user) => $user->isStaff());
```

## Set a resource up

```php
$room = BookableResource::create([
    'name'               => 'Consultation Room A',
    'slug'               => 'room-a',
    'timezone'           => 'America/Chicago',
    'slot_minutes'       => 30,
    'buffer_minutes'     => 10,   // gap after each booking
    'capacity'           => 1,    // >1 for a class or a room with seats
    'min_notice_minutes' => 120,
    'advance_days'       => 60,
]);

// Mon–Fri, 09:00–17:00 LOCAL to the resource.
foreach (range(1, 5) as $day) {
    $room->availability()->create([
        'day_of_week' => $day,     // 0 = Sunday, matching Carbon
        'opens_at'    => '09:00',
        'closes_at'   => '17:00',
    ]);
}

$room->blackouts()->create([
    'starts_at' => '2026-12-24 00:00',
    'ends_at'   => '2026-12-27 00:00',
    'reason'    => 'Holiday',
]);
```

Then send people to `/book/room-a`.

## Design decisions worth knowing

**The double-booking race is closed with a row lock, not a check.** Two people
hitting the last slot milliseconds apart both pass the availability check;
only `lockForUpdate` inside a transaction makes the second one lose. Checking
without locking is the bug that is in nearly every hand-rolled version of this.

**Availability is computed in the resource's timezone.** Opening hours are
wall-clock: "we open at 9" means 9am local, which is a different UTC instant
either side of a DST change. Computing in UTC and converting at the end shifts
every slot by an hour twice a year. Everything is *stored* UTC.

**Overlap is half-open.** A booking ending exactly when another starts does not
conflict. Using `>=` on both sides makes every back-to-back slot look like a
clash and silently halves a day's capacity.

**The end time is derived, never sent by the client.** A client-supplied end
lets someone book three hours on a thirty-minute resource.

**The requested window is checked against the generated slots.** Without that,
anyone can POST a 3am appointment on a closed day and it is perfectly valid.

**A resource with no hours has no slots.** Not "always open", which is what an
empty-means-anything default produces on the day someone forgets to configure
it.

**A pending booking still holds the slot.** Otherwise, under the `request`
option, two people are both told yes and one gets a phone call later.

**Approval re-checks capacity.** The slot may have filled while the request sat
pending, and approving past capacity is exactly the overbooking this prevents.

**An inactive resource answers 404 on both endpoints.** Answering 404 for
availability and 422 for booking tells a stranger the resource exists.

**The reference is random, not the id.** It is the secret behind the public
cancel link.

**Cancelling twice is not an error.** A double-click on Cancel.

**The availability window is capped at 62 days per request,** so one call
cannot ask for five years of half-hour slots.

## Frontend

- `BookingPage.vue` — `ROUTES.BOOKING` → `/book/:slug`. Props: `slug`, `days`.
  Outside the auth pipeline, because booking usually happens before anyone has
  an account.
- `AppSlotPicker` — slots grouped by day. Times are shown in the **resource's**
  timezone and labelled as such: the appointment is at the resource, not at the
  viewer, and a silently-converted time is how people show up an hour late.
- `useBooking(slug)` — `resource`, `slots`, `byDay`, `loading`, `booking`,
  `confirmed`, `fetchAvailability(from, to)`, `book(slot, details)`, plus
  `timeLabel` / `dayLabel` / `tz` formatters. A 409 or 422 refreshes
  availability, so the picker stops offering a slot that is already gone.
