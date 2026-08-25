<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Modules\Otp\Notifications\OtpCodeMail;
use Modules\Otp\Support\StepUpStore;

/**
 * The `login+step-up` variant only. The `login` choice drops this file.
 */
beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create(['email' => 'person@example.com']);
});

function stepUpCode(): string
{
    $sent = null;
    Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$sent) {
        $sent = $mail->code;

        return true;
    });

    return (string) $sent;
}

test('step-up requires being signed in', function () {
    $this->postJson('/api/v1/otp/step-up/request')->assertUnauthorized();
});

test('a signed-in user can request and verify a step-up code', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/otp/step-up/request')
        ->assertOk()
        ->assertJsonPath('masked', 'p•••••@example.com');

    $this->actingAs($this->user)
        ->postJson('/api/v1/otp/step-up/verify', ['code' => stepUpCode()])
        ->assertOk();
});

test('a login code does not satisfy a step-up', function () {
    // Different purposes are different codes. Otherwise the code emailed to
    // sign in would also authorise deleting the account.
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    $loginCode = stepUpCode();

    $this->actingAs($this->user)
        ->postJson('/api/v1/otp/step-up/verify', ['code' => $loginCode])
        ->assertStatus(422);
});

test('the middleware refuses a request with no step-up', function () {
    Route::middleware(['api', 'auth:sanctum', 'otp.step-up'])
        ->post('/api/v1/_test/danger', fn () => response()->json(['ok' => true]));

    // 428, not 401 — a bare 401 makes the frontend log the person out instead
    // of opening the step-up dialog.
    $this->actingAs($this->user)
        ->postJson('/api/v1/_test/danger')
        ->assertStatus(428)
        ->assertJsonPath('stepUp', true);
});

test('the middleware lets a recently stepped-up request through', function () {
    Route::middleware(['api', 'auth:sanctum', 'otp.step-up'])
        ->post('/api/v1/_test/danger', fn () => response()->json(['ok' => true]));

    app(StepUpStore::class)->mark($this->user);

    $this->actingAs($this->user)
        ->postJson('/api/v1/_test/danger')
        ->assertOk();
});

test('a step-up on one device does not step up another', function () {
    // Keyed by access token, not by user — a session left open elsewhere is
    // the whole threat model. Asserted against the store rather than over
    // HTTP: the test client resolves the user once and keeps its access token,
    // so two bearer requests in one test would share a token and the property
    // would pass for the wrong reason.
    $store = app(StepUpStore::class);

    $laptop = $this->user->createToken('laptop')->accessToken;
    $phone  = $this->user->createToken('phone')->accessToken;

    $onLaptop = $this->user->withAccessToken($laptop);
    $store->mark($onLaptop);

    expect($store->isVerified($onLaptop))->toBeTrue()
        ->and($store->isVerified($this->user->fresh()->withAccessToken($phone)))->toBeFalse();
});

test('a stale step-up no longer counts', function () {
    Route::middleware(['api', 'auth:sanctum', 'otp.step-up'])
        ->post('/api/v1/_test/danger', fn () => response()->json(['ok' => true]));

    // Marked, then the window moves past it.
    app(StepUpStore::class)->mark($this->user);
    $this->travel(2)->hours();

    $this->actingAs($this->user)
        ->postJson('/api/v1/_test/danger')
        ->assertStatus(428);
});
