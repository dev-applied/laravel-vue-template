<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Announcements\Jobs\SendAnnouncementEmails;
use Modules\Announcements\Mail\AnnouncementMail;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Support\AudienceResolver;

/**
 * The `in-app+email` variant only. The `in-app` choice drops this file along
 * with the job and the mailable.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    Gate::define('manage-announcements', fn () => true);
    config()->set('announcements.email', true);
});

test('publishing queues the email send', function () {
    Queue::fake();
    $announcement = Announcement::factory()->draft()->create();

    $this->actingAs($this->user)
        ->postJson("/api/v1/announcements/{$announcement->id}/publish")
        ->assertOk();

    Queue::assertPushed(
        SendAnnouncementEmails::class,
        fn (SendAnnouncementEmails $job) => $job->announcementId === $announcement->id
    );
});

test('publishing an already-published announcement does not re-send', function () {
    Queue::fake();
    $announcement = Announcement::factory()->create();

    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

    Queue::assertNothingPushed();
});

test('email delivery stays off unless the option turned it on', function () {
    Queue::fake();
    config()->set('announcements.email', false);
    $announcement = Announcement::factory()->draft()->create();

    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

    Queue::assertNothingPushed();
});

test('the job mails everyone the resolver returns', function () {
    Mail::fake();
    User::factory()->count(2)->create();
    $announcement = Announcement::factory()->create();

    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));

    // 3 users: the one from beforeEach plus the two above.
    Mail::assertSent(AnnouncementMail::class, 3);
});

test('a job for an unpublished announcement sends nothing', function () {
    // Unpublished between dispatch and pickup — the worker must notice.
    Mail::fake();
    $announcement = Announcement::factory()->draft()->create();

    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));

    Mail::assertNothingSent();
});

test('a deleted announcement does not crash the worker', function () {
    Mail::fake();
    $announcement = Announcement::factory()->create();
    $id           = $announcement->id;
    $announcement->delete();

    (new SendAnnouncementEmails($id))->handle(app(AudienceResolver::class));

    Mail::assertNothingSent();
});

// ── Sending exactly once ─────────────────────────────────────────────────────
// Two ways the whole audience used to get mailed twice: publishing raced with
// itself, and the job's retry restarted at the first recipient. Both are now
// settled by the unique index on announcement_deliveries rather than by where
// a loop happens to be.

test('the publish claim is on the update, not on a prior read', function () {
    // Two clicks that arrive together both saw published_at === null, both
    // wrote it, and both queued the job. A single-threaded test cannot make
    // them arrive together, so this pins the mechanism: the condition has to
    // be part of the UPDATE, where exactly one of them can win it.
    Queue::fake();
    $announcement = Announcement::factory()->draft()->create();

    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    $this->actingAs($this->user)
        ->postJson("/api/v1/announcements/{$announcement->id}/publish")
        ->assertOk();

    $publish = collect($statements)->first(
        fn (string $sql) => str_contains($sql, 'update') && str_contains($sql, 'published_at` = ?')
    );

    expect($publish)->not->toBeNull('the announcement was never published')
        ->and($publish)->toContain('`published_at` is null');
});

test('a retry does not mail anyone who was already sent to', function () {
    // The expensive one. The job retries three times; before this, each retry
    // restarted at the first recipient and mailed the whole audience again.
    Mail::fake();

    $announcement = Announcement::factory()->create();
    $recipients   = User::factory()->count(3)->create();

    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));
    Mail::assertSentCount(User::query()->count());

    // Exactly what the queue does after a worker dies mid-send.
    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));

    Mail::assertSentCount(User::query()->count());
    expect($recipients)->not->toBeEmpty();
});

test('a resumed send still delivers to the people it had not reached', function () {
    // The other half of the same guarantee: skipping the already-sent must not
    // become skipping everyone. This is the state a worker killed part-way
    // through leaves behind.
    Mail::fake();

    $announcement = Announcement::factory()->create();
    $already      = User::factory()->create(['email' => 'first@example.com']);
    $pending      = User::factory()->create(['email' => 'second@example.com']);

    DB::table('announcement_deliveries')->insert([
        'announcement_id' => $announcement->id,
        'recipient'       => 'first@example.com',
        'sent_at'         => now(),
    ]);

    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));

    Mail::assertNotSent(AnnouncementMail::class, fn ($mail) => $mail->hasTo($already->email));
    Mail::assertSent(AnnouncementMail::class, fn ($mail) => $mail->hasTo($pending->email));
});

test('the address is claimed before the send, so a throwing mailer cannot cause a duplicate', function () {
    // At-most-once is the deliberate choice for a broadcast. A send that threw
    // may still have been accepted upstream, so retrying it risks mailing
    // everyone twice to fix a maybe-missed one — and the recipient sees the
    // announcement in-app regardless.
    Mail::fake();
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp is down'));

    $announcement = Announcement::factory()->create();
    User::factory()->create(['email' => 'unlucky@example.com']);

    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));

    expect(DB::table('announcement_deliveries')
        ->where('announcement_id', $announcement->id)
        ->where('recipient', 'unlucky@example.com')
        ->exists())->toBeTrue();
});

test('unpublishing and publishing again does not mail the audience a second time', function () {
    // Unpublish/republish is how a typo gets corrected, not how a re-send is
    // requested. Mailing the whole base again because someone fixed a comma is
    // the failure people actually notice.
    Mail::fake();

    $announcement = Announcement::factory()->create();
    User::factory()->count(2)->create();

    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));
    $first = Mail::sent(AnnouncementMail::class)->count();

    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/unpublish")->assertOk();
    $this->actingAs($this->user)->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertOk();

    (new SendAnnouncementEmails($announcement->id))->handle(app(AudienceResolver::class));

    expect(Mail::sent(AnnouncementMail::class)->count())->toBe($first);
});
