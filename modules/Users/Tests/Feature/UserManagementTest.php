<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * deactivated_at is not in the kernel's User::$fillable, so factory attributes
 * for it are silently dropped. Set it explicitly.
 */
function deactivated(array $attributes = []): User
{
    $user                 = User::factory()->create($attributes);
    $user->deactivated_at = now();
    $user->save();

    return $user;
}

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
    Gate::define('manage-users', fn () => true);
});

test('users are listed', function () {
    User::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/manage/users')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('search matches first name, last name and email', function () {
    User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.com']);

    foreach (['Grace', 'Hopper', 'grace@'] as $term) {
        $names = $this->actingAs($this->admin)
            ->getJson('/api/v1/manage/users?search='.urlencode($term))
            ->json('data.*.firstName');

        expect($names)->toBe(['Grace']);
    }
});

test('the search filter does not leak when combined with another', function () {
    // The kernel's version chained ->where(...)->orWhere(...) UNGROUPED, so
    // the moment another constraint joins the query the OR escapes it and the
    // filter returns rows it was meant to exclude.
    User::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    deactivated(['first_name' => 'Zed', 'last_name' => 'Zulu']);

    $names = $this->actingAs($this->admin)
        ->getJson('/api/v1/manage/users?search=Grace&status=deactivated')
        ->json('data.*.firstName');

    // Grace is active, so a deactivated-only search for "Grace" is empty. An
    // ungrouped OR would have returned Zed.
    expect($names)->toBe([]);
});

test('the status filter separates active from deactivated', function () {
    deactivated(['first_name' => 'Gone']);

    $active = $this->actingAs($this->admin)->getJson('/api/v1/manage/users?status=active')->json('data.*.firstName');
    $off    = $this->actingAs($this->admin)->getJson('/api/v1/manage/users?status=deactivated')->json('data.*.firstName');

    expect($active)->toBe(['Ada'])
        ->and($off)->toBe(['Gone']);
});

test('creating without a password sends a set-password link', function () {
    // Safer default: a password typed by an admin is a password that has been
    // typed into a chat window.
    $this->actingAs($this->admin)
        ->postJson('/api/v1/manage/users', [
            'first_name' => 'New', 'last_name' => 'Person', 'email' => 'new@example.com',
        ])
        ->assertCreated()
        ->assertJsonPath('invitedByMail', true);

    Notification::assertSentTo(User::where('email', 'new@example.com')->first(), ResetPassword::class);
});

test('a created account has an unusable password, never a null one', function () {
    // A null password column makes every downstream Hash::check a potential
    // fatal, and some auth paths treat an empty hash as a match.
    $this->actingAs($this->admin)->postJson('/api/v1/manage/users', [
        'first_name' => 'New', 'last_name' => 'Person', 'email' => 'new@example.com',
    ])->assertCreated();

    $created = User::where('email', 'new@example.com')->firstOrFail();

    expect($created->password)->not->toBeNull()
        ->and($created->password)->not->toBe('')
        ->and(Hash::check('', $created->password))->toBeFalse();
});

test('creating with a password does not send a link', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/manage/users', [
            'first_name' => 'New', 'last_name' => 'Person', 'email' => 'new@example.com',
            'password'   => 'Str0ng-Passw0rd!', 'password_confirmation' => 'Str0ng-Passw0rd!',
        ])
        ->assertCreated()
        ->assertJsonPath('invitedByMail', false);

    Notification::assertNothingSent();
});

test('emails are normalised and must be unique', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/manage/users', [
        'first_name' => 'A', 'last_name' => 'B', 'email' => '  MiXeD@Example.COM ',
    ])->assertCreated();

    expect(User::where('email', 'mixed@example.com')->exists())->toBeTrue();

    $this->actingAs($this->admin)->postJson('/api/v1/manage/users', [
        'first_name' => 'C', 'last_name' => 'D', 'email' => 'MIXED@example.com',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

test('a user can be renamed', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/manage/users/{$user->id}", ['first_name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('firstName', 'Renamed');
});

test('saving a user without changing their email is not a uniqueness error', function () {
    $user = User::factory()->create(['email' => 'same@example.com']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/manage/users/{$user->id}", ['email' => 'same@example.com', 'first_name' => 'Edited'])
        ->assertOk();
});

test('changing an email clears the verification', function () {
    // The new address has not proved anything.
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/manage/users/{$user->id}", ['email' => 'changed@example.com'])
        ->assertOk()
        ->assertJsonPath('emailVerified', false);
});

test('an empty password on update leaves the existing one alone', function () {
    // Edit forms post an empty password field constantly.
    $user   = User::factory()->create();
    $before = $user->password;

    $this->actingAs($this->admin)
        ->putJson("/api/v1/manage/users/{$user->id}", ['first_name' => 'Edited', 'password' => null])
        ->assertOk();

    expect($user->fresh()->password)->toBe($before);
});

test('deactivating hides them and signs them out everywhere', function () {
    // Without revoking tokens, "deactivated" means nothing until they expire.
    $user = User::factory()->create();
    $user->createToken('phone');

    $this->actingAs($this->admin)
        ->postJson("/api/v1/manage/users/{$user->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('isActive', false);

    expect($user->fresh()->deactivated_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0);
});

test('a deactivated user can be reactivated', function () {
    $user = deactivated();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/manage/users/{$user->id}/reactivate")
        ->assertOk()
        ->assertJsonPath('isActive', true);
});

test('you cannot deactivate yourself', function () {
    // The admin who deactivated themselves and could not get back in.
    User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/manage/users/{$this->admin->id}/deactivate")
        ->assertStatus(422);

    expect($this->admin->fresh()->deactivated_at)->toBeNull();
});

test('you cannot delete yourself', function () {
    User::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/manage/users/{$this->admin->id}")
        ->assertStatus(422);

    expect(User::find($this->admin->id))->not->toBeNull();
});

test('the last active account cannot be removed', function () {
    // The other half of the lockout: deleting the only other admin and leaving
    // an org with nobody who can add one.
    $other = User::factory()->create();
    $this->actingAs($this->admin)->postJson("/api/v1/manage/users/{$this->admin->id}/deactivate");

    // Only $other is active now, and $other is acting.
    $this->actingAs($other)
        ->deleteJson("/api/v1/manage/users/{$other->id}")
        ->assertStatus(422);
});

test('a deactivated account does not count towards the last-active check', function () {
    // A deactivated account cannot let anyone back in, so it must not be what
    // makes deleting the last active one look safe.
    $dormant = deactivated();
    $target  = User::factory()->create();

    $this->actingAs($this->admin)->postJson("/api/v1/manage/users/{$this->admin->id}/deactivate");

    $this->actingAs($target)
        ->deleteJson("/api/v1/manage/users/{$target->id}")
        ->assertStatus(422);

    expect(User::find($dormant->id))->not->toBeNull();
});

test('another user can be deleted when others remain', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/manage/users/{$user->id}")
        ->assertOk();

    expect(User::find($user->id))->toBeNull();
});

test('the response marks the viewers own row', function () {
    // So the UI hides destructive controls rather than offering them and then
    // 422ing.
    $isSelf = $this->actingAs($this->admin)
        ->getJson("/api/v1/manage/users/{$this->admin->id}")
        ->json('isSelf');

    expect($isSelf)->toBeTrue();
});

test('user management is gated', function () {
    Gate::define('manage-users', fn () => false);

    $this->actingAs($this->admin)->getJson('/api/v1/manage/users')->assertForbidden();
    $this->actingAs($this->admin)->postJson('/api/v1/manage/users', [])->assertForbidden();
});

test('user management requires authentication', function () {
    $this->getJson('/api/v1/manage/users')->assertUnauthorized();
});

test('a password must meet the default policy', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/manage/users', [
            'first_name' => 'A', 'last_name' => 'B', 'email' => 'weak@example.com',
            'password'   => 'abc', 'password_confirmation' => 'abc',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('an untouched password field posts as an empty string and is treated as absent', function () {
    // A form that only edits a name still submits password="". `nullable` does
    // not catch that — "" is present, so it hits the min-length rule and the
    // save is rejected for a password nobody typed.
    $user = User::factory()->create(['first_name' => 'Old']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/manage/users/{$user->id}", [
            'first_name'            => 'New',
            'password'              => '',
            'password_confirmation' => '',
        ])
        ->assertOk();

    expect($user->fresh()->first_name)->toBe('New');
});

test('an empty password on create still sends a set-password link rather than 422ing', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/manage/users', [
            'first_name' => 'Empty',
            'last_name'  => 'Password',
            'email'      => 'empty@example.com',
            'password'   => '',
        ])
        ->assertCreated();

    expect(User::where('email', 'empty@example.com')->exists())->toBeTrue();
});

test('a real password is still validated when one is typed', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/manage/users/{$user->id}", [
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('with no project-defined gate, management is denied rather than open', function () {
    // Fail-closed matters more than usual here: an open default would hand every
    // signed-in user the ability to delete accounts.
    expect(Gate::allows('manage-users', User::factory()->create()))->toBeFalse();
});

test('the last-active guard holds a lock, so the check and the write are one step', function () {
    // Two admins are the only active accounts and each deactivates the other at
    // the same moment: both counts return "1 remaining", both pass, both writes
    // land, and there are now ZERO active users. There is no in-app recovery —
    // role management sits behind an account nobody can sign into, so it takes
    // shell access or direct SQL. Two admins removing each other during a
    // handover is a real thing that happens.
    //
    // A genuine race cannot be provoked from a single-threaded test, so this
    // pins the property that makes the race impossible: the guard runs inside a
    // transaction, and it takes a row lock while it counts.
    $guard = app(Modules\Users\Support\UserGuard::class);
    $other = User::factory()->create();

    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });

    $guard->protecting($other, fn () => $other->forceFill(['deactivated_at' => now()])->save());

    $counted = collect($queries)->first(fn (string $sql) => str_contains($sql, 'count(*)'));

    expect($counted)->not->toBeNull()
        ->and($counted)->toContain('for update');
});
