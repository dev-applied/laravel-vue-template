<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Models\AnnouncementDismissal;

beforeEach(function () {
    $this->user = User::factory()->create();
    Gate::define('manage-announcements', fn () => true);
});

test('a live announcement reaches a signed-in user', function () {
    $announcement = Announcement::factory()->create(['title' => 'Scheduled maintenance']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertOk()
        ->assertJsonCount(1, 'announcements')
        ->assertJsonPath('announcements.0.id', $announcement->id)
        ->assertJsonPath('announcements.0.title', 'Scheduled maintenance');
});

test('a draft is not live', function () {
    Announcement::factory()->draft()->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonPath('announcements', []);
});

test('an announcement scheduled for the future is not live yet', function () {
    Announcement::factory()->scheduled()->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonPath('announcements', []);
});

test('an expired announcement drops out on its own', function () {
    // No cron, no cleanup job — the window is evaluated at read time, so an
    // announcement stops showing the moment it ends.
    Announcement::factory()->expired()->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonPath('announcements', []);
});

test('a null window means live from publish until unpublished', function () {
    Announcement::factory()->create(['starts_at' => null, 'ends_at' => null]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonCount(1, 'announcements');
});

test('dismissing hides it for that user only', function () {
    $announcement = Announcement::factory()->create();
    $other        = User::factory()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonPath('announcements', []);

    $this->actingAs($other)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonCount(1, 'announcements');
});

test('a dismissal survives a reload', function () {
    // The whole reason dismissal is a table row and not localStorage.
    $announcement = Announcement::factory()->create();

    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/dismiss");

    expect(AnnouncementDismissal::where('user_id', $this->user->id)->count())->toBe(1);
});

test('dismissing twice writes one row, not two', function () {
    // A double-click on a slow button is the normal case. Two rows would break
    // the dismissal count the authoring UI reports.
    $announcement = Announcement::factory()->create();

    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/dismiss");
    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/dismiss")->assertOk();

    expect(AnnouncementDismissal::count())->toBe(1);
});

test('a non-dismissible announcement refuses dismissal', function () {
    $announcement = Announcement::factory()->create(['dismissible' => false]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertStatus(422);

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonCount(1, 'announcements');
});

test('an announcement requiring acknowledgement records when it was acknowledged', function () {
    $announcement = Announcement::factory()->mustAcknowledge()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertOk();

    $dismissal = AnnouncementDismissal::firstOrFail();

    expect($dismissal->acknowledged_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonPath('announcements', []);
});

test('the loudest level sorts first', function () {
    Announcement::factory()->create(['level' => Announcement::LEVEL_INFO, 'title' => 'info']);
    Announcement::factory()->create(['level' => Announcement::LEVEL_ERROR, 'title' => 'error']);
    Announcement::factory()->create(['level' => Announcement::LEVEL_WARNING, 'title' => 'warning']);

    $titles = $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->json('announcements.*.title');

    expect($titles)->toBe(['error', 'warning', 'info']);
});

test('an unknown audience fails closed', function () {
    // The default resolver knows only `everyone`. An unknown audience that
    // defaulted to "show it" would broadcast to the whole user base, and there
    // is no un-sending that.
    Announcement::factory()->create(['audience' => 'billing:pro']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonPath('announcements', []);
});

test('publishing sets published_at', function () {
    $announcement = Announcement::factory()->draft()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/announcements/{$announcement->id}/publish")
        ->assertOk()
        ->assertJsonPath('isLive', true);

    expect($announcement->fresh()->published_at)->not->toBeNull();
});

test('publishing twice does not reset the clock', function () {
    $announcement = Announcement::factory()->create(['published_at' => now()->subDays(3)]);
    $original     = $announcement->published_at;

    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

    expect($announcement->fresh()->published_at->timestamp)->toBe($original->timestamp);
});

test('unpublishing pulls it down immediately', function () {
    $announcement = Announcement::factory()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/announcements/{$announcement->id}/unpublish")
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertJsonPath('announcements', []);
});

test('a window that closes before it opens is rejected', function () {
    // Otherwise it is simply never shown, and nothing in the UI says why.
    $this->actingAs($this->user)
        ->postJson('/api/v1/announcements', [
            'title'     => 'Backwards',
            'body'      => 'Body',
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at'   => now()->toIso8601String(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ends_at');
});

test('an action label without a url is rejected', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/announcements', [
            'title'        => 'Half a button',
            'body'         => 'Body',
            'action_label' => 'Read more',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('action_url');
});

test('the authoring endpoints are gated', function () {
    Gate::define('manage-announcements', fn () => false);

    $this->actingAs($this->user)->getJson('/api/v1/announcements')->assertForbidden();
    $this->actingAs($this->user)->postJson('/api/v1/announcements', [])->assertForbidden();
});

test('a reader can still read the active feed without the manage ability', function () {
    Gate::define('manage-announcements', fn () => false);
    Announcement::factory()->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/announcements/active')
        ->assertOk()
        ->assertJsonCount(1, 'announcements');
});

test('the active feed requires authentication', function () {
    $this->getJson('/api/v1/announcements/active')->assertUnauthorized();
});

test('the authoring index filters by status', function () {
    Announcement::factory()->create(['title' => 'live one']);
    Announcement::factory()->draft()->create(['title' => 'draft one']);

    $titles = $this->actingAs($this->user)
        ->getJson('/api/v1/announcements?status=draft')
        ->assertOk()
        ->json('data.*.title');

    expect($titles)->toBe(['draft one']);
});

// ---------------------------------------------------------------------------
// Dismissal was unfiltered
// ---------------------------------------------------------------------------

test('a draft cannot be acknowledged', function () {
    // index() applies live() and the audience resolver; dismiss() applied
    // neither, so any authenticated user could acknowledge an unpublished
    // policy — and requires_acknowledgement exists precisely to be a
    // defensible record of who was shown what. A record of someone
    // acknowledging a notice never displayed to them is worse than no record,
    // because it reads as evidence.
    $announcement = Announcement::factory()->create([
        'published_at'             => null,
        'requires_acknowledgement' => true,
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertNotFound();

    expect(AnnouncementDismissal::query()->count())->toBe(0);
});

test('an announcement that has not started cannot be acknowledged', function () {
    $announcement = Announcement::factory()->create([
        'published_at'             => now()->subDay(),
        'starts_at'                => now()->addWeek(),
        'requires_acknowledgement' => true,
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertNotFound();
});

test('an expired announcement cannot be acknowledged', function () {
    $announcement = Announcement::factory()->create([
        'published_at'             => now()->subMonth(),
        'ends_at'                  => now()->subDay(),
        'requires_acknowledgement' => true,
    ]);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertNotFound();
});

test('acknowledging twice keeps the FIRST timestamp', function () {
    // `['acknowledged_at' => now()]` in an update payload restamps on every
    // call, so a second dismiss moved the record later than the person
    // actually accepted. The column exists to say WHEN.
    $announcement = Announcement::factory()->create([
        'published_at'             => now()->subDay(),
        'requires_acknowledgement' => true,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertOk();

    $first = AnnouncementDismissal::query()->firstOrFail()->acknowledged_at;

    $this->travel(2)->hours();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/dismiss")
        ->assertOk();

    expect(AnnouncementDismissal::query()->count())->toBe(1)
        ->and(AnnouncementDismissal::query()->firstOrFail()->acknowledged_at->toDateTimeString())
        ->toBe($first->toDateTimeString());
});
