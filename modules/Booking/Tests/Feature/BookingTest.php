<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Modules\Booking\Models\BookableResource;
use Modules\Booking\Models\Booking;
use Modules\Booking\Support\AvailabilityCalculator;

/**
 * Fixed clock. Availability maths that only passes on a Tuesday is not
 * tested, it is lucky.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-03-02 08:00:00', 'UTC'));   // a Monday

    // Pinned, because this file ships in BOTH variants and the `request`
    // install sets the env flag globally. A shared test that silently changes
    // meaning with an install option is testing the option, not the code.
    config()->set('booking.requires_approval', false);

    $this->user = User::factory()->create();
    Gate::define('manage-bookings', fn () => true);

    $this->resource = BookableResource::factory()->create([
        'slug'         => 'room-a',
        'slot_minutes' => 30,
        'capacity'     => 1,
    ]);

    // Mon–Fri, 09:00–12:00 local.
    foreach (range(1, 5) as $day) {
        $this->resource->availability()->create([
            'day_of_week' => $day,
            'opens_at'    => '09:00',
            'closes_at'   => '12:00',
        ]);
    }
});

afterEach(fn () => Carbon::setTestNow());

function slotsFor($resource, string $from, string $to): array
{
    return app(AvailabilityCalculator::class)->slots($resource, Carbon::parse($from), Carbon::parse($to));
}

test('a day of availability produces the expected slots', function () {
    // 09:00-12:00 in 30-minute slots = 6.
    $slots = slotsFor($this->resource, '2026-03-03 00:00', '2026-03-03 23:59');

    expect($slots)->toHaveCount(6)
        ->and($slots[0]['starts_at'])->toStartWith('2026-03-03T09:00:00');
});

test('a resource with no hours has no slots', function () {
    // Not "always open" — which is what an empty-means-anything default would
    // produce the day someone forgets to set hours up.
    $bare = BookableResource::factory()->create(['slug' => 'bare']);

    expect(slotsFor($bare, '2026-03-03 00:00', '2026-03-03 23:59'))->toBe([]);
});

test('a closed day has no slots', function () {
    // Sunday.
    expect(slotsFor($this->resource, '2026-03-08 00:00', '2026-03-08 23:59'))->toBe([]);
});

test('a buffer spaces the slots out', function () {
    $this->resource->update(['buffer_minutes' => 30]);

    // 30-minute slot plus 30-minute buffer = one an hour, so 3 in 09:00-12:00.
    expect(slotsFor($this->resource, '2026-03-03 00:00', '2026-03-03 23:59'))->toHaveCount(3);
});

test('a booked slot disappears at capacity 1', function () {
    Booking::factory()->create([
        'bookable_resource_id' => $this->resource->id,
        'starts_at'            => Carbon::parse('2026-03-03 09:00', 'UTC'),
        'ends_at'              => Carbon::parse('2026-03-03 09:30', 'UTC'),
    ]);

    $slots = slotsFor($this->resource, '2026-03-03 00:00', '2026-03-03 23:59');

    expect($slots)->toHaveCount(5)
        ->and($slots[0]['starts_at'])->toStartWith('2026-03-03T09:30:00');
});

test('a booked slot stays available while capacity remains', function () {
    $this->resource->update(['capacity' => 3]);
    Booking::factory()->create([
        'bookable_resource_id' => $this->resource->id,
        'starts_at'            => Carbon::parse('2026-03-03 09:00', 'UTC'),
        'ends_at'              => Carbon::parse('2026-03-03 09:30', 'UTC'),
    ]);

    $slots = slotsFor($this->resource, '2026-03-03 00:00', '2026-03-03 23:59');

    expect($slots)->toHaveCount(6)
        ->and($slots[0]['remaining'])->toBe(2);
});

test('a cancelled booking frees its slot', function () {
    Booking::factory()->cancelled()->create([
        'bookable_resource_id' => $this->resource->id,
        'starts_at'            => Carbon::parse('2026-03-03 09:00', 'UTC'),
        'ends_at'              => Carbon::parse('2026-03-03 09:30', 'UTC'),
    ]);

    expect(slotsFor($this->resource, '2026-03-03 00:00', '2026-03-03 23:59'))->toHaveCount(6);
});

test('back-to-back bookings do not count as overlapping', function () {
    // A half-open comparison. Using >= on both sides makes every consecutive
    // slot look like a conflict and silently halves the day.
    Booking::factory()->create([
        'bookable_resource_id' => $this->resource->id,
        'starts_at'            => Carbon::parse('2026-03-03 09:00', 'UTC'),
        'ends_at'              => Carbon::parse('2026-03-03 09:30', 'UTC'),
    ]);

    $overlapping = Booking::query()->overlapping(
        Carbon::parse('2026-03-03 09:30', 'UTC'),
        Carbon::parse('2026-03-03 10:00', 'UTC')
    )->count();

    expect($overlapping)->toBe(0);
});

test('a blackout removes the slots it covers', function () {
    $this->resource->blackouts()->create([
        'starts_at' => Carbon::parse('2026-03-03 09:00', 'UTC'),
        'ends_at'   => Carbon::parse('2026-03-03 10:00', 'UTC'),
        'reason'    => 'Maintenance',
    ]);

    expect(slotsFor($this->resource, '2026-03-03 00:00', '2026-03-03 23:59'))->toHaveCount(4);
});

test('minimum notice hides slots that are too soon', function () {
    // now() is Monday 08:00; 120 minutes notice means nothing before 10:00.
    $this->resource->update(['min_notice_minutes' => 120]);

    $slots = slotsFor($this->resource, '2026-03-02 00:00', '2026-03-02 23:59');

    expect($slots[0]['starts_at'])->toStartWith('2026-03-02T10:00:00');
});

test('the advance window hides slots too far out', function () {
    $this->resource->update(['advance_days' => 1]);

    // Wednesday is two days away.
    expect(slotsFor($this->resource, '2026-03-04 00:00', '2026-03-04 23:59'))->toBe([]);
});

test('availability is computed in the resource timezone', function () {
    // 09:00 in New York is 14:00 UTC in March (EDT), not 09:00 UTC. Computing
    // in UTC and converting at the end shifts every slot.
    $this->resource->update(['timezone' => 'America/New_York']);

    $slots = slotsFor($this->resource, '2026-03-03 00:00', '2026-03-04 06:00');

    expect($slots[0]['starts_at'])->toStartWith('2026-03-03T14:00:00');
});

test('the availability endpoint returns slots', function () {
    $this->getJson('/api/v1/booking/room-a/availability?from=2026-03-03&to=2026-03-04')
        ->assertOk()
        ->assertJsonPath('resource.slug', 'room-a')
        ->assertJsonCount(6, 'slots');
});

test('the availability window is capped', function () {
    // So one request cannot ask for five years of half-hour slots.
    $this->getJson('/api/v1/booking/room-a/availability?from=2026-03-03&to=2027-03-03')
        ->assertStatus(422);
});

test('an inactive resource is not publicly visible', function () {
    $this->resource->update(['is_active' => false]);

    $this->getJson('/api/v1/booking/room-a/availability?from=2026-03-03&to=2026-03-04')
        ->assertNotFound();
});

test('booking an offered slot succeeds', function () {
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z',
        'name'      => 'Jane Doe',
        'email'     => 'jane@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonStructure(['reference']);
});

test('the end time is derived, never taken from the client', function () {
    // A client-supplied end lets someone book three hours on a 30-minute
    // resource.
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z',
        'ends_at'   => '2026-03-03T12:00:00Z',
        'name'      => 'Jane',
        'email'     => 'jane@example.com',
    ])->assertCreated();

    expect(Booking::first()->ends_at->toIso8601String())->toStartWith('2026-03-03T09:30:00');
});

test('a time the resource does not offer is refused', function () {
    // Otherwise anyone can POST a 3am appointment on a closed day.
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T03:00:00Z',
        'name'      => 'Night Owl',
        'email'     => 'owl@example.com',
    ])->assertStatus(422);

    expect(Booking::count())->toBe(0);
});

test('a slot on a closed day is refused', function () {
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-08T09:00:00Z',
        'name'      => 'Sunday',
        'email'     => 'sun@example.com',
    ])->assertStatus(422);
});

test('a second booking on a full slot is refused', function () {
    $payload = ['starts_at' => '2026-03-03T09:00:00Z', 'name' => 'A', 'email' => 'a@example.com'];

    $this->postJson('/api/v1/booking/room-a', $payload)->assertCreated();
    $this->postJson('/api/v1/booking/room-a', [...$payload, 'name' => 'B', 'email' => 'b@example.com'])
        ->assertStatus(422);

    expect(Booking::count())->toBe(1);
});

test('capacity allows concurrent bookings up to the limit', function () {
    $this->resource->update(['capacity' => 2]);
    $payload = ['starts_at' => '2026-03-03T09:00:00Z', 'name' => 'A', 'email' => 'a@example.com'];

    $this->postJson('/api/v1/booking/room-a', $payload)->assertCreated();
    $this->postJson('/api/v1/booking/room-a', [...$payload, 'email' => 'b@example.com'])->assertCreated();
    $this->postJson('/api/v1/booking/room-a', [...$payload, 'email' => 'c@example.com'])->assertStatus(422);

    expect(Booking::count())->toBe(2);
});

test('an inactive resource refuses bookings the same way it hides them', function () {
    // 404 on both endpoints. Whether an inactive resource exists at a
    // guessable slug is not public information, and answering 404 in one place
    // and 422 in the other tells you it does.
    $this->resource->update(['is_active' => false]);

    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'A', 'email' => 'a@example.com',
    ])->assertStatus(404);

    $this->getJson('/api/v1/booking/room-a/availability?from=2026-03-03&to=2026-03-04')
        ->assertStatus(404);
});

test('a reference looks a booking up', function () {
    $reference = $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Jane', 'email' => 'jane@example.com',
    ])->json('reference');

    // The public lookup returns PublicBookingResource, which deliberately
    // omits name/email/notes — a leaked reference should not leak the booker.
    $this->getJson("/api/v1/booking/reference/{$reference}")
        ->assertOk()
        ->assertJsonPath('reference', $reference)
        ->assertJsonMissingPath('name');
});

test('a reference is random, not the id', function () {
    // The reference IS the secret for the public cancel link.
    $reference = $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Jane', 'email' => 'jane@example.com',
    ])->json('reference');

    expect($reference)->toHaveLength(10)
        ->and($reference)->toMatch('/^[0-9A-F]{10}$/')
        ->and($reference)->not->toBe((string) Booking::first()->id);
});

test('cancelling frees the slot', function () {
    $reference = $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Jane', 'email' => 'jane@example.com',
    ])->json('reference');

    $this->postJson("/api/v1/booking/reference/{$reference}/cancel", ['email' => 'jane@example.com'])
        ->assertOk()
        ->assertJsonPath('status', 'cancelled');

    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Next', 'email' => 'next@example.com',
    ])->assertCreated();
});

test('cancelling twice is not an error', function () {
    // A double-click on Cancel.
    $reference = $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Jane', 'email' => 'jane@example.com',
    ])->json('reference');

    $this->postJson("/api/v1/booking/reference/{$reference}/cancel", ['email' => 'jane@example.com'])->assertOk();
    $this->postJson("/api/v1/booking/reference/{$reference}/cancel", ['email' => 'jane@example.com'])->assertOk();
});

test('an unknown reference is a 404', function () {
    $this->getJson('/api/v1/booking/reference/NOPENOPE')->assertNotFound();
});

test('a signed-in booking records the user', function () {
    $this->actingAs($this->user)->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Jane', 'email' => 'jane@example.com',
    ])->assertCreated();

    expect(Booking::first()->user_id)->toBe($this->user->id);
});

test('the admin list is gated', function () {
    Gate::define('manage-bookings', fn () => false);

    $this->actingAs($this->user)->getJson('/api/v1/bookings')->assertForbidden();
});

test('the admin list filters to upcoming', function () {
    Booking::factory()->create([
        'bookable_resource_id' => $this->resource->id,
        'starts_at'            => now()->subDays(2),
        'ends_at'              => now()->subDays(2)->addMinutes(30),
    ]);
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Future', 'email' => 'f@example.com',
    ]);

    $names = $this->actingAs($this->user)->getJson('/api/v1/bookings?upcoming=1')->json('data.*.name');

    expect($names)->toBe(['Future']);
});

// ---------------------------------------------------------------------------
// The public reference endpoints
//
// `reference` is the whole credential here, and it is not a secret in practice:
// it is printed on the confirmation, quoted in support threads, screenshotted
// and forwarded.
// ---------------------------------------------------------------------------

test('cancelling needs the booking email, not just the reference', function () {
    $booking = Booking::factory()->create(['email' => 'ada@example.com', 'status' => Booking::STATUS_CONFIRMED]);

    $this->postJson("/api/v1/booking/reference/{$booking->reference}/cancel")
        ->assertJsonValidationErrors('email');

    $this->postJson("/api/v1/booking/reference/{$booking->reference}/cancel", ['email' => 'someone@else.test'])
        ->assertNotFound();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
});

test('the right email still cancels — the flow the confirmation email drives', function () {
    $booking = Booking::factory()->create(['email' => 'ada@example.com', 'status' => Booking::STATUS_CONFIRMED]);

    $this->postJson("/api/v1/booking/reference/{$booking->reference}/cancel", ['email' => 'ADA@Example.com'])
        ->assertOk();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

test('a wrong email and an unknown reference are indistinguishable', function () {
    // Otherwise the endpoint confirms which references exist.
    $booking = Booking::factory()->create(['email' => 'ada@example.com']);

    $wrongEmail = $this->postJson("/api/v1/booking/reference/{$booking->reference}/cancel", ['email' => 'x@y.test']);
    $noSuchRef  = $this->postJson('/api/v1/booking/reference/NOPE12345/cancel', ['email' => 'x@y.test']);

    expect($wrongEmail->status())->toBe($noSuchRef->status())
        ->and($wrongEmail->json('message'))->toBe($noSuchRef->json('message'));
});

test('the public lookup does not hand back the email or the notes', function () {
    // It used to return the same resource the staff listing uses, so a leaked
    // reference leaked the booker's address and their free-text notes with it.
    // On a clinic or legal resource, notes IS the sensitive field.
    $booking = Booking::factory()->create([
        'email' => 'ada@example.com',
        'name'  => 'Ada Lovelace',
        'notes' => 'Bringing the results of the biopsy',
    ]);

    $body = $this->getJson("/api/v1/booking/reference/{$booking->reference}")
        ->assertOk()
        ->json();

    expect($body)->not->toHaveKey('email')
        ->and($body)->not->toHaveKey('notes')
        ->and($body)->not->toHaveKey('name')
        ->and($body['reference'])->toBe($booking->reference)
        ->and($body['startsAt'])->not->toBeNull();
});

test('the reference is not folded onto a skewed alphabet', function () {
    // Str::upper(Str::random(8)) maps 62 symbols onto 36 non-uniformly — a
    // letter arrives twice as often as a digit — costing ~7 bits on the value
    // that IS the credential.
    $references = collect(range(1, 40))->map(fn () => Booking::factory()->create()->reference);

    expect($references->unique())->toHaveCount(40)
        ->and($references->first())->toMatch('/^[0-9A-F]{10}$/');
});

test('approving an already-confirmed booking is idempotent, not a second confirm', function () {
    // approve() had neither transaction nor lock while book() twelve lines up
    // had both. The conditional update makes a repeat observable instead of
    // silently re-confirming.
    $resource = BookableResource::factory()->create(['capacity' => 1]);
    $booking  = Booking::factory()->for($resource, 'resource')->create(['status' => Booking::STATUS_PENDING]);

    $service = app(Modules\Booking\Support\BookingService::class);

    expect($service->approve($booking)->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($service->approve($booking->fresh())->status)->toBe(Booking::STATUS_CONFIRMED);
});

test('the resource block names the RESOURCE, not the person who booked', function () {
    // Booking has a relation called `resource`, and JsonResource has its own
    // $resource property holding the wrapped model. Every other field in
    // BookingResource reaches the model through @mixin magic, so
    // `$this->resource->name` reads as "the related resource's name" and is not
    // — it returned the BOOKER'S name, with null slug and null timezone,
    // because a booking row has no such columns.
    //
    // The public confirmation page rendered it as "Where: Dana Visitor". Found
    // by booking a slot in a browser, with the suite green.
    $booking = Booking::factory()->create([
        'bookable_resource_id' => $this->resource->getKey(),
        'name'                 => 'Dana Visitor',
        'email'                => 'dana@example.com',
    ]);

    $payload = (new Modules\Booking\Http\Resources\BookingResource($booking->load('resource')))
        ->toArray(request());

    expect($payload['name'])->toBe('Dana Visitor')
        ->and($payload['resource']['name'])->toBe($this->resource->name)
        ->and($payload['resource']['name'])->not->toBe('Dana Visitor')
        ->and($payload['resource']['slug'])->toBe('room-a')
        ->and($payload['resource']['timezone'])->not->toBeNull();
});

test('the resource block is omitted when the relation was not loaded', function () {
    // The other half: fixing the collision must not turn `when(...)` into
    // something that always fires and lazy-loads a query per serialized row.
    $booking = Booking::factory()->create(['bookable_resource_id' => $this->resource->getKey()]);

    $payload = (new Modules\Booking\Http\Resources\BookingResource($booking))->toArray(request());

    expect($payload['resource'])->toBeInstanceOf(Illuminate\Http\Resources\MissingValue::class);
});
