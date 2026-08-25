<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Modules\Realtime\Support\ChannelGuards;

beforeEach(function () {
    // A REAL broadcaster, not the `null` one the test environment defaults to.
    // NullBroadcaster::auth() authorises everything, so a suite left on it
    // asserts that unguarded channels are refused and gets a 200 — the check
    // passes in production and is untested here, which is the worse direction.
    config()->set('broadcasting.default', 'reverb');
    config()->set('realtime.client.key', 'test-key');
    config()->set('broadcasting.connections.reverb', [
        'driver'  => 'reverb',
        'key'     => 'test-key',
        'secret'  => 'test-secret',
        'app_id'  => 'test-app',
        'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
    ]);
});

test('the client config is public and says whether realtime is configured', function () {
    // Public by design: the app KEY is not a secret — the secret is what signs
    // the auth response, and that never leaves the server. The client needs
    // this before it can authenticate anything.
    config()->set('realtime.client.key', '');

    $body = $this->getJson('/api/v1/realtime/config')->assertOk()->json('data');

    expect($body['enabled'])->toBeFalse()
        ->and($body)->not->toHaveKey('secret');

    config()->set('realtime.client.key', 'local-key');

    expect($this->getJson('/api/v1/realtime/config')->json('data.enabled'))->toBeTrue();
});

test('the config is served rather than baked in', function () {
    // A Capacitor build is compiled once and pointed at an API afterwards, so a
    // VITE_ variable frozen at build time is the wrong host from then on.
    config()->set('realtime.client.host', 'staging.example.com');

    expect($this->getJson('/api/v1/realtime/config')->json('data.host'))->toBe('staging.example.com');
});

test('broadcast auth is behind sanctum, not the web guard', function () {
    // The default Broadcast::routes() uses `web`. An SPA on cookie auth works
    // either way; a Capacitor build on a bearer token does not, and the failure
    // is a socket that connects and then authorises no private channel at all.
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'private-orders.1',
    ])->assertUnauthorized();
});

test('a signed-in user is still refused a channel nobody guards', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'private-nobody-declared-this',
    ])->assertForbidden();
});

test('a guarded channel authorises the user it names and refuses the rest', function () {
    $owner    = User::factory()->create();
    $stranger = User::factory()->create();

    app(ChannelGuards::class)->define(
        'user.{id}',
        fn (User $user, string $id) => (int) $user->id === (int) $id,
    );

    $this->actingAs($owner)->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => "private-user.{$owner->id}",
    ])->assertOk();

    $this->actingAs($stranger)->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => "private-user.{$owner->id}",
    ])->assertForbidden();
});

test('a presence guard returning true produces an empty member record', function () {
    // Why presence() exists as its own method: `true` is a valid authorisation
    // AND an empty member payload, so a presence channel guarded like a private
    // one connects fine and shows nobody. Pinned so the docblock is not the
    // only thing saying it.
    $user = User::factory()->create(['first_name' => 'Ada']);

    Broadcast::channel('room.{id}', fn (User $u, string $id) => true);

    $payload = $this->actingAs($user)->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'presence-room.7',
    ])->assertOk()->json();

    expect(data_get($payload, 'channel_data.user_info'))->toBeIn([null, [], true]);

    // …whereas returning an array is what a member list is made of.
    app(ChannelGuards::class)->presence(
        'lounge.{id}',
        fn (User $u, string $id) => ['id' => $u->id, 'name' => $u->first_name],
    );

    $good = $this->actingAs($user)->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'presence-lounge.7',
    ])->assertOk()->json();

    expect(data_get($good, 'channel_data'))->toBeString();
    expect(json_decode(data_get($good, 'channel_data'), true)['user_info']['name'])->toBe('Ada');
});

test('auth refuses everything while broadcasting is unconfigured', function () {
    // The `null` broadcaster authorises every channel, so without this the
    // endpoint rubber-stamps until somebody sets a real driver — and then they
    // inherit an auth path that has never once said no. Measured in the running
    // app before the guard existed: a signed-in user POSTing an undeclared
    // private channel got 200.
    config()->set('broadcasting.default', 'null');

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'private-anything',
    ])->assertForbidden()
        ->assertJsonPath('message', 'Realtime is not configured on this environment.');
});

test('auth also refuses when no app key is set', function () {
    config()->set('realtime.client.key', '');

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/broadcasting/auth', [
        'socket_id'    => '123.456',
        'channel_name' => 'private-anything',
    ])->assertForbidden();
});

test('the registry records what it defined', function () {
    $guards = app(ChannelGuards::class);

    $guards->define('thing.{id}', fn () => true);
    $guards->presence('place.{id}', fn () => ['id' => 1]);

    expect($guards->defined())->toContain('thing.{id}')->toContain('place.{id}');
});
