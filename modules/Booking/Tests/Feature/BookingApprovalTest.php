<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Modules\Booking\Models\BookableResource;

/**
 * The `request` variant only. The `instant` choice drops this file.
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

test('a booking arrives pending when approval is required', function () {
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'Jane', 'email' => 'jane@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending');
});

test('a pending booking still holds the slot', function () {
    // Otherwise two people are told yes for the same time and one gets a
    // phone call later.
    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'A', 'email' => 'a@example.com',
    ])->assertCreated();

    $this->postJson('/api/v1/booking/room-a', [
        'starts_at' => '2026-03-03T09:00:00Z', 'name' => 'B', 'email' => 'b@example.com',
    ])->assertStatus(422);
});
