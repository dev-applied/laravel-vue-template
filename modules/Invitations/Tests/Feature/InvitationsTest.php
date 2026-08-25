<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Invitations\Mail\InvitationMail;
use Modules\Invitations\Models\Invitation;

beforeEach(function () {
    // The management routes are gated on `manage-invitations`, which the module
    // registers fail-closed. Existing tests act as somebody permitted; the
    // escalation tests at the bottom deliberately do not.
    Gate::define('manage-invitations', fn () => true);
    Gate::define('assign-any-role', fn () => true);

    Mail::fake();
    $this->admin = User::factory()->create();
});

test('sending an invitation emails a link and stores only a hash', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/invitations', ['email' => 'New@Example.com'])
        ->assertCreated()
        ->assertJsonPath('invitation.email', 'new@example.com')
        ->assertJsonPath('invitation.status', 'pending');

    $invitation = Invitation::query()->sole();

    // 64 hex chars = sha256. The plaintext must not be recoverable from the row.
    expect($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->getAttributes())->not->toHaveKey('token');

    Mail::assertSent(InvitationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
});

test('the token hash is hidden from serialisation', function () {
    $invitation = Invitation::factory()->create();

    expect(array_keys($invitation->toArray()))->not->toContain('token_hash');
});

test('inviting an existing user reveals nothing and sends nothing', function () {
    // This endpoint must not become a membership oracle.
    User::factory()->create(['email' => 'known@example.com']);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/invitations', ['email' => 'known@example.com']);

    $response->assertStatus(202)->assertJsonPath('sent', true);

    expect(Invitation::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('a valid token previews the invited email', function () {
    $invitation = Invitation::issue('new@example.com', null, $this->admin->id);

    $this->getJson('/api/v1/invitations/accept?token='.$invitation->plain_token)
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('email', 'new@example.com');
});

test('accepting creates the account and returns a usable token', function () {
    $invitation = Invitation::issue('new@example.com', null, $this->admin->id);

    $this->postJson('/api/v1/invitations/accept', [
        'token'                 => $invitation->plain_token,
        'first_name'            => 'Ada',
        'last_name'             => 'Lovelace',
        'password'              => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])
        ->assertCreated()
        ->assertJsonStructure(['access_token', 'token_type']);

    $user = User::query()->where('email', 'new@example.com')->sole();

    expect($user->first_name)->toBe('Ada')
        ->and($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and($invitation->fresh()->user_id)->toBe($user->id);
});

test('a token cannot be used twice', function () {
    $invitation = Invitation::issue('new@example.com', null, $this->admin->id);
    $payload    = [
        'token'    => $invitation->plain_token, 'first_name' => 'Ada', 'last_name' => 'L',
        'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery',
    ];

    $this->postJson('/api/v1/invitations/accept', $payload)->assertCreated();
    $this->postJson('/api/v1/invitations/accept', $payload)->assertNotFound();

    expect(User::query()->where('email', 'new@example.com')->count())->toBe(1);
});

test('an expired token is rejected', function () {
    $token = Str::random(64);
    Invitation::factory()->expired()->create(['token_hash' => Invitation::hashToken($token)]);

    $this->getJson('/api/v1/invitations/accept?token='.$token)->assertNotFound();
    $this->postJson('/api/v1/invitations/accept', [
        'token'    => $token, 'first_name' => 'A', 'last_name' => 'B',
        'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery',
    ])->assertNotFound();
});

test('a revoked token is rejected', function () {
    $token = Str::random(64);
    Invitation::factory()->revoked()->create(['token_hash' => Invitation::hashToken($token)]);

    $this->getJson('/api/v1/invitations/accept?token='.$token)->assertNotFound();
});

test('an unknown token is rejected with the same message as an expired one', function () {
    // Distinguishing them tells a guesser which tokens exist.
    $unknown = $this->getJson('/api/v1/invitations/accept?token='.Str::random(64));
    $token   = Str::random(64);
    Invitation::factory()->expired()->create(['token_hash' => Invitation::hashToken($token)]);
    $expired = $this->getJson('/api/v1/invitations/accept?token='.$token);

    expect($unknown->json('message'))->toBe($expired->json('message'));
});

test('resending issues a new token and kills the old link', function () {
    $first = Invitation::issue('new@example.com', null, $this->admin->id);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invitations/{$first->id}/resend")
        ->assertOk();

    // The original link must stop working the moment a new one is sent.
    $this->getJson('/api/v1/invitations/accept?token='.$first->plain_token)->assertNotFound();

    expect($first->fresh()->revoked_at)->not->toBeNull()
        ->and(Invitation::query()->pending()->where('email', 'new@example.com')->count())->toBe(1);
});

test('issuing twice never leaves two live invitations for one email', function () {
    Invitation::issue('new@example.com', null, $this->admin->id);
    Invitation::issue('new@example.com', null, $this->admin->id);
    Invitation::issue('new@example.com', null, $this->admin->id);

    expect(Invitation::query()->pending()->where('email', 'new@example.com')->count())->toBe(1);
});

test('revoking an invitation keeps the record but kills the link', function () {
    $invitation = Invitation::issue('new@example.com', null, $this->admin->id);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/invitations/{$invitation->id}")
        ->assertOk()
        ->assertJsonPath('invitation.status', 'revoked');

    // Revoked, not deleted — "who was invited then un-invited" stays answerable.
    expect(Invitation::query()->whereKey($invitation->id)->exists())->toBeTrue();
    $this->getJson('/api/v1/invitations/accept?token='.$invitation->plain_token)->assertNotFound();
});

test('accepting requires a confirmed password', function () {
    $invitation = Invitation::issue('new@example.com', null, $this->admin->id);

    $this->postJson('/api/v1/invitations/accept', [
        'token'    => $invitation->plain_token, 'first_name' => 'A', 'last_name' => 'B',
        'password' => 'correct-horse-battery', 'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

test('the listing reports a status for every lifecycle state', function () {
    Invitation::factory()->create();
    Invitation::factory()->accepted()->create();
    Invitation::factory()->revoked()->create();
    Invitation::factory()->expired()->create();

    $statuses = $this->actingAs($this->admin)
        ->getJson('/api/v1/invitations')
        ->assertOk()
        ->json('data.*.status');

    expect($statuses)->toContain('pending', 'accepted', 'revoked', 'expired');
});

test('managing invitations requires authentication', function () {
    $this->getJson('/api/v1/invitations')->assertUnauthorized();
    $this->postJson('/api/v1/invitations', ['email' => 'a@example.com'])->assertUnauthorized();
});

test('the prune command removes spent invitations but keeps live ones', function () {
    Invitation::factory()->accepted()->create(['created_at' => now()->subDays(60)]);
    Invitation::factory()->revoked()->create(['created_at' => now()->subDays(60)]);
    Invitation::factory()->expired()->create(['created_at' => now()->subDays(60)]);
    Invitation::factory()->create(['created_at' => now()->subDays(60)]);  // still pending

    $this->artisan('invitations:prune', ['--days' => 30])->assertSuccessful();

    expect(Invitation::query()->count())->toBe(1)
        ->and(Invitation::query()->sole()->status())->toBe('pending');
});

test('expires_at carries no ON UPDATE clause', function () {
    // Same regression as Otp, same reasoning: a NOT NULL `timestamp` with no
    // default picks up ON UPDATE CURRENT_TIMESTAMP on MariaDB, so ANY write to
    // an invitation row rewrites its expiry to now(). A seven-day token that
    // silently resets its own clock on every update is not a seven-day token.
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Column metadata check is MySQL/MariaDB-specific.');
    }

    foreach (DB::select('SHOW COLUMNS FROM invitations') as $column) {
        expect(mb_strtolower((string) $column->Extra))
            ->not->toContain('on update', "invitations.{$column->Field} carries an ON UPDATE clause");
    }
});

// ---------------------------------------------------------------------------
// Privilege escalation
//
// The management routes carried `auth:sanctum` and nothing else, and `role` was
// a free string handed straight to assignRole() on accept. Any authenticated
// user could invite an address they control, name any role, accept it, and land
// in a fresh account holding it.
// ---------------------------------------------------------------------------

test('an ordinary user cannot invite anybody', function () {
    Gate::define('manage-invitations', fn () => false);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/invitations', ['email' => 'them@example.com'])
        ->assertForbidden();
});

test('inviting is denied when the project has defined no gate', function () {
    // Fail-closed. An undefined gate meaning "allow" is how this comes back.
    Gate::define('manage-invitations', fn () => Gate::has('__never__'));

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/invitations', ['email' => 'them@example.com'])
        ->assertForbidden();
});

test('a role that does not exist is refused', function () {
    // It was `string|max:125` and nothing else, so `super-admin` sailed through
    // whether or not such a role existed.
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/invitations', ['email' => 'them@example.com', 'role' => 'super-admin'])
        ->assertJsonValidationErrors('role');
});

test('an inviter cannot hand out a role they do not hold themselves', function () {
    // Otherwise "may invite people" quietly means "may create a super-admin",
    // and the route gate is not the boundary it looks like.
    //
    // The role is created first ON PURPOSE: without it this would fail the
    // exists rule instead, and pass for a reason that has nothing to do with
    // the escalation control it is meant to pin.
    if (! Schema::hasTable('roles')) {
        $this->markTestSkipped('Needs the RolesPermissions module.');
    }

    DB::table('roles')->insert(['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

    Gate::define('assign-any-role', fn () => false);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/invitations', ['email' => 'them@example.com', 'role' => 'admin'])
        ->assertJsonValidationErrors('role');
});

test('an inviter CAN hand out a role they hold', function () {
    // The other side of the same rule — without this, "refuse everything" would
    // pass the test above just as well.
    if (! Schema::hasTable('roles')) {
        $this->markTestSkipped('Needs the RolesPermissions module.');
    }

    DB::table('roles')->insert(['name' => 'editor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

    Gate::define('assign-any-role', fn () => false);

    $inviter = User::factory()->create();

    if (! method_exists($inviter, 'assignRole')) {
        $this->markTestSkipped('Needs spatie roles on the User model.');
    }

    $inviter->assignRole('editor');

    $this->actingAs($inviter, 'sanctum')
        ->postJson('/api/v1/invitations', ['email' => 'them@example.com', 'role' => 'editor'])
        ->assertCreated();
});

test('a real role passes when the inviter may assign any role', function () {
    // The rule must not simply refuse everything — that would satisfy both
    // negative tests above while breaking the feature. This is the positive
    // that does not need spatie's trait on the User model, which the PROJECT
    // applies, not this module.
    if (! Schema::hasTable('roles')) {
        $this->markTestSkipped('Needs the RolesPermissions module.');
    }

    DB::table('roles')->insert(['name' => 'support', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

    Gate::define('assign-any-role', fn () => true);

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/invitations', ['email' => 'them@example.com', 'role' => 'support'])
        ->assertCreated();
});

test('a failed send does not destroy the invitation it was replacing', function () {
    // issue() revokes every pending invitation for the address before minting a
    // new one — right, because two live tokens for one person is a second way
    // in that nobody is tracking. But the send used to happen after it and
    // outside any transaction, so an SMTP failure left the original revoked and
    // the replacement undelivered: a resend that never arrived destroyed the
    // working link the invitee already had.
    $original = Invitation::issue('ada@example.com', null, null);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp is down'));

    $admin = User::factory()->create();
    Gate::define('invite-users', fn () => true);

    $this->actingAs($admin)
        ->postJson("/api/v1/invitations/{$original->id}/resend")
        ->assertStatus(500);

    expect($original->fresh()->revoked_at)->toBeNull()
        ->and(Invitation::query()->where('email', 'ada@example.com')->count())->toBe(1);
});
