<?php

declare(strict_types=1);

use App\Models\User;

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
