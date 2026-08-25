<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\AuditLog\Models\AuditLog;
use Modules\AuditLog\Tests\Support\AuditableUser;

beforeEach(function () {
    $this->actor = User::factory()->create();
    // The module refuses to guess who may read the trail; the project defines
    // this gate. Open it for the suite.
    Gate::define('viewAuditLog', fn () => true);
});

test('creating an audited model records a created entry', function () {
    $this->actingAs($this->actor);

    $subject = AuditableUser::create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'      => 'ada@example.com', 'password' => 'secret-value',
    ]);

    $log = AuditLog::query()->sole();

    expect($log->event)->toBe(AuditLog::EVENT_CREATED)
        ->and($log->auditable_id)->toBe($subject->id)
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->new_values)->toHaveKey('first_name');
});

test('secrets are never written to the trail', function () {
    $this->actingAs($this->actor);

    AuditableUser::create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'      => 'ada@example.com', 'password' => 'secret-value',
    ]);

    $log = AuditLog::query()->sole();

    expect($log->new_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('remember_token')
        ->and(json_encode($log->new_values))->not->toContain('secret-value');
});

test('an excluded column is kept out of the trail', function () {
    $this->actingAs($this->actor);

    AuditableUser::create([
        'first_name'        => 'Ada', 'last_name' => 'Lovelace',
        'email'             => 'ada@example.com', 'password' => 'x',
        'email_verified_at' => now(),
    ]);

    expect(AuditLog::query()->sole()->new_values)->not->toHaveKey('email_verified_at');
});

test('an update records only the fields that actually changed', function () {
    $subject = AuditableUser::create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'      => 'ada@example.com', 'password' => 'x',
    ]);
    AuditLog::query()->delete();

    $this->actingAs($this->actor);
    $subject->update(['first_name' => 'Grace']);

    $log = AuditLog::query()->sole();

    expect($log->event)->toBe(AuditLog::EVENT_UPDATED)
        ->and($log->changedFields())->toBe(['first_name'])
        ->and($log->old_values['first_name'])->toBe('Ada')
        ->and($log->new_values['first_name'])->toBe('Grace');
});

test('a save that changes nothing but the timestamp records no entry', function () {
    $subject = AuditableUser::create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'      => 'ada@example.com', 'password' => 'x',
    ]);
    AuditLog::query()->delete();

    $subject->touch();

    expect(AuditLog::query()->count())->toBe(0);
});

test('deleting an audited model records a deleted entry with its final state', function () {
    $subject = AuditableUser::create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'      => 'ada@example.com', 'password' => 'x',
    ]);
    AuditLog::query()->delete();

    $subject->delete();

    $log = AuditLog::query()->sole();

    expect($log->event)->toBe(AuditLog::EVENT_DELETED)
        ->and($log->old_values['first_name'])->toBe('Ada');
});

test('entries are still recorded when nobody is authenticated', function () {
    // Console commands, jobs and seeders all write without a user. Dropping
    // those entries would make the trail lie by omission.
    AuditableUser::create([
        'first_name' => 'System', 'last_name' => 'Job',
        'email'      => 'job@example.com', 'password' => 'x',
    ]);

    expect(AuditLog::query()->sole()->user_id)->toBeNull();
});

test('the log lists entries newest first with the actor loaded', function () {
    AuditLog::factory()->count(3)->create();

    $this->actingAs($this->actor)
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.changes.0.field', 'first_name');
});

test('the log can be filtered to one subject', function () {
    $mine = AuditLog::factory()->create(['auditable_type' => User::class, 'auditable_id' => 4242]);
    AuditLog::factory()->count(2)->create();

    $this->actingAs($this->actor)
        ->getJson('/api/v1/audit-logs?auditable_type='.urlencode(User::class).'&auditable_id=4242')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

test('the log can be filtered by event', function () {
    AuditLog::factory()->create(['event' => AuditLog::EVENT_CREATED]);
    AuditLog::factory()->count(2)->create(['event' => AuditLog::EVENT_UPDATED]);

    $this->actingAs($this->actor)
        ->getJson('/api/v1/audit-logs?event=created')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('the trail is unreadable without the project gate', function () {
    Gate::define('viewAuditLog', fn () => false);

    $this->actingAs($this->actor)
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden();
});

test('audit routes require authentication', function () {
    $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
});

test('the prune command deletes entries past the retention window', function () {
    AuditLog::factory()->count(2)->create(['created_at' => now()->subDays(400)]);
    AuditLog::factory()->count(3)->create(['created_at' => now()->subDays(10)]);

    $this->artisan('audit:prune', ['--days' => 365])->assertSuccessful();

    expect(AuditLog::query()->count())->toBe(3);
});

test('the record timeline relation returns that subject entries only', function () {
    $subject = AuditableUser::create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email'      => 'ada@example.com', 'password' => 'x',
    ]);
    AuditLog::factory()->count(2)->create();

    expect($subject->auditLogs()->count())->toBe(1);
});
