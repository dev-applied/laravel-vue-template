<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\Auth\Mail\ForgotPasswordMail;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'reset@example.com']);
});

test('forgot password sends the module reset notification', function () {
    Notification::fake();

    $this->postJson('/api/v1/forgot-password', ['email' => 'reset@example.com'])
        ->assertOk();

    Notification::assertSentTo($this->user, ResetPassword::class);
});

test('forgot password does not reveal unknown emails', function () {
    Notification::fake();

    // Deliberately 200 for unknown addresses so the endpoint can't be used
    // to enumerate accounts.
    $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk();

    Notification::assertNothingSent();
});

test('reset mail renders from the module view namespace', function () {
    $html = (new ForgotPasswordMail('the-token', $this->user))->render();

    expect($html)
        ->toContain('the-token')
        ->toContain('/set-password');
});

test('reset mail is addressed to the user', function () {
    $mail = (new ForgotPasswordMail('the-token', $this->user))->to($this->user);

    expect($mail->hasTo('reset@example.com'))->toBeTrue();
});

test('password can be reset with a valid token', function () {
    $token = Password::createToken($this->user);

    $this->postJson('/api/v1/forgot-password/reset', [
        'email'                 => 'reset@example.com',
        'token'                 => $token,
        'password'              => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    $this->postJson('/api/v1/auth', [
        'email'    => 'reset@example.com',
        'password' => 'new-password-123',
    ])->assertOk();
});
