<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Modules\Booking\Models\BookableResource;
use Modules\Booking\Models\Booking;

/**
 * The approve ENDPOINT, in a file no option variant drops.
 *
 * These used to live in BookingApprovalTest, which `approval=instant` prunes —
 * while keeping the route, the controller method and the service method, all
 * registered unconditionally. So the instant build shipped an endpoint whose
 * gate and whose capacity re-check had no test at all.
 *
 * And the endpoint is not dead code there, which is the part that makes this
 * matter: pending-versus-confirmed is decided by `config('booking.requires_approval')`
 * at RUNTIME, not by the file drop. An `instant` install that later sets
 * BOOKING_REQUIRES_APPROVAL=true starts producing pending bookings and needs
 * every one of these guards.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-03-02 08:00:00', 'UTC'));
    config()->set('booking.requires_approval', true);

    $this->user = User::factory()->create();
    Gate::define('manage-bookings', fn () => true);

    $this->resource = BookableResource::factory()->create(['slug' => 'room-a', 'capacity' => 1]);

    foreach (range(1, 5) as $day) {
        $this->resource->availability()->create([
            'day_of_week' => $day, 'opens_at' => '09:00', 'closes_at' => '12:00',
        ]);
    }
});

afterEach(fn () => Carbon::setTestNow());

test('approving confirms it', function () {
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Jane', 'email' => 'jane@example.com',
    ]);

    $booking = Booking::firstOrFail();

    $this->actingAs($this->user)
        ->postJson("/api/v1/bookings/{$booking->id}/approve")
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');
});

test('approving re-checks capacity', function () {
    // The slot may have filled while this one sat pending, and approving past
    // capacity is exactly the overbooking this module exists to prevent.
    $pending = Booking::factory()->create([
        'bookable_resource_id' => $this->resource->id,
        'status'               => Booking::STATUS_PENDING,
        'starts_at'            => Carbon::parse('2026-03-03 09:00', 'UTC'),
        'ends_at'              => Carbon::parse('2026-03-03 09:30', 'UTC'),
    ]);

    Booking::factory()->create([
        'bookable_resource_id' => $this->resource->id,
        'status'               => Booking::STATUS_CONFIRMED,
        'starts_at'            => Carbon::parse('2026-03-03 09:00', 'UTC'),
        'ends_at'              => Carbon::parse('2026-03-03 09:30', 'UTC'),
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/bookings/{$pending->id}/approve")
        ->assertStatus(409);
});

test('a cancelled booking cannot be approved', function () {
    $booking = Booking::factory()->cancelled()->create(['bookable_resource_id' => $this->resource->id]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/bookings/{$booking->id}/approve")
        ->assertStatus(422);
});

test('approving is gated', function () {
    Gate::define('manage-bookings', fn () => false);
    $booking = Booking::factory()->create(['bookable_resource_id' => $this->resource->id]);

    $this->actingAs($this->user)->postJson("/api/v1/bookings/{$booking->id}/approve")->assertForbidden();
});
