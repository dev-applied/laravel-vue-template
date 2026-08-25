<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email'    => 'test@example.com',
        'password' => 'password',
    ]);
});

test('user can login with valid credentials', function () {
    $response = $this->postJson('/api/v1/auth', [
        'email'    => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type']);
});

test('user cannot login with invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth', [
        'email'    => 'test@example.com',
        'password' => 'wrong',
    ]);

    $response->assertUnprocessable();
});

test('authenticated user can get their profile', function () {
    $token = $this->user->createToken('test')->plainTextToken;

    $response = $this->getJson('/api/v1/auth', [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', 'test@example.com');
});

test('user can logout', function () {
    $token = $this->user->createToken('test')->plainTextToken;

    $response = $this->deleteJson('/api/v1/auth', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk();

    // Reset the resolved guard so Sanctum re-checks the token
    app('auth')->forgetGuards();

    // Token should be revoked. /api/v1/auth is deliberately public (it must
    // answer guests), so a revoked token gets 200 with user: null — never 401.
    $this->getJson('/api/v1/auth', [
        'Authorization' => "Bearer {$token}",
    ])->assertOk()->assertJsonPath('user', null);
});

test('login is rate limited', function () {
    // throttle:6,1 on the login route: six attempts pass through (each a
    // 422 for the wrong password), the seventh is throttled.
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/auth', [
            'email'    => 'test@example.com',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth', [
        'email'    => 'test@example.com',
        'password' => 'wrong',
    ])->assertTooManyRequests();
});

// ---------------------------------------------------------------------------
// Impersonation
//
// There were no tests here at all, and the endpoint carried `auth:sanctum` and
// nothing else — so any authenticated user could post a user_id and receive a
// working bearer token for that account, super-admins included. One request,
// total takeover. These exist so that cannot come back quietly.
// ---------------------------------------------------------------------------

test('an ordinary user cannot impersonate anybody', function () {
    $victim = User::factory()->create();
    $actor  = User::factory()->create();

    $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/auth/impersonate', ['user_id' => $victim->id])
        ->assertForbidden();
});

test('impersonation is denied when the project has defined no gate', function () {
    // Fail-closed is the whole design. An undefined gate meaning "allow" is
    // exactly how the original hole would return.
    $victim = User::factory()->create();
    $actor  = User::factory()->create();

    $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/auth/impersonate', ['user_id' => $victim->id])
        ->assertForbidden();

    expect(Laravel\Sanctum\PersonalAccessToken::query()->count())->toBe(0);
});

test('a permitted user can impersonate, and the token works', function () {
    $victim = User::factory()->create();
    $actor  = User::factory()->create();

    Gate::define('impersonate-users', fn (User $user) => $user->is($actor));

    $token = $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/auth/impersonate', ['user_id' => $victim->id])
        ->assertOk()
        ->json('access_token');

    expect($token)->toBeString();

    // actingAs() binds the guard for the whole test, so without this the next
    // request answers as the ACTOR and the Bearer header is never consulted —
    // which would make this assert nothing at all.
    auth()->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth')
        ->assertOk()
        ->assertJsonPath('user.id', $victim->id);
});

test('the impersonation token is bounded in time', function () {
    // An unbounded token for another account is a permanent second credential
    // for it, long after whatever prompted the impersonation is over.
    $victim = User::factory()->create();
    $actor  = User::factory()->create();

    Gate::define('impersonate-users', fn () => true);

    $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/auth/impersonate', ['user_id' => $victim->id])
        ->assertOk();

    $token = Laravel\Sanctum\PersonalAccessToken::query()
        ->where('name', 'impersonation-token')
        ->firstOrFail();

    expect($token->expires_at)->not->toBeNull();
});

test('impersonating yourself is refused rather than minting a second token', function () {
    $actor = User::factory()->create();

    Gate::define('impersonate-users', fn () => true);

    $this->actingAs($actor, 'sanctum')
        ->postJson('/api/v1/auth/impersonate', ['user_id' => $actor->id])
        ->assertStatus(422);
});

test('an impersonation session is reported as one', function () {
    // The banner cannot decide to show itself otherwise, and an impersonating
    // session that looks identical to a real one is the whole hazard: somebody
    // forgets, and then acts as that user believing they are themselves.
    $user  = User::factory()->create();
    $token = $user->createToken('impersonation-token', ['impersonated'])->plainTextToken;

    expect($this->withToken($token)->getJson('/api/v1/auth')->assertOk()->json('impersonating'))
        ->toBeTrue();
});

test('an ordinary session is not', function () {
    // Its own test, not a second assertion above: two authenticated requests in
    // one test share a resolved guard, and the first token's user is still the
    // one answering when the second arrives.
    //
    // A plain login token holds the `*` wildcard, which is why this cannot be
    // checked with Sanctum's can() — that answers true for any ability, so
    // every normal session would report as an impersonation.
    $user  = User::factory()->create();
    $token = $user->createToken('normal')->plainTextToken;

    expect($this->withToken($token)->getJson('/api/v1/auth')->assertOk()->json('impersonating'))
        ->toBeFalse();
});

test('a guest is not reported as impersonating', function () {
    $this->getJson('/api/v1/auth')->assertOk()->assertJson(['user' => null, 'impersonating' => false]);
});
