<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login:127.0.0.1');

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

    // Token should be revoked
    $this->getJson('/api/v1/auth', [
        'Authorization' => "Bearer {$token}",
    ])->assertUnauthorized();
});

test('login is rate limited', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/auth', [
            'email'    => 'test@example.com',
            'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/v1/auth', [
        'email'    => 'test@example.com',
        'password' => 'wrong',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
