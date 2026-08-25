<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Otp\Models\OtpCode;
use Modules\Otp\Notifications\OtpCodeMail;
use Modules\Otp\Support\OtpManager;

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('otp:id:');
    $this->user = User::factory()->create(['email' => 'person@example.com']);
    $this->otp  = app(OtpManager::class);
});

/** The plaintext code, which only the channel ever sees. */
function sentCode(): string
{
    $sent = null;
    Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$sent) {
        $sent = $mail->code;

        return true;
    });

    return (string) $sent;
}

test('requesting a code emails one', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com'])
        ->assertOk()
        ->assertJsonPath('masked', 'p•••••@example.com');

    Mail::assertSent(OtpCodeMail::class);
});

test('the code is hashed at rest', function () {
    // A database read should not hand someone a working credential.
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com'])->assertOk();

    $record = OtpCode::firstOrFail();

    expect($record->code_hash)->not->toBe(sentCode())
        ->and(Hash::check(sentCode(), $record->code_hash))->toBeTrue();
});

test('a valid code returns a token', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);

    $this->postJson('/api/v1/otp/verify', [
        'identifier' => 'person@example.com',
        'code'       => sentCode(),
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
});

test('a code works only once', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    $code = sentCode();

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => $code])->assertOk();
    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => $code])->assertStatus(422);
});

test('requesting a new code retires the old one', function () {
    // Two live codes means the older keeps working after the person asked for
    // a new one — exactly the window an attacker wants.
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    $first = sentCode();

    Mail::fake();
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    $second = sentCode();

    expect($first)->not->toBe($second);

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => $first])
        ->assertStatus(422);
    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => $second])
        ->assertOk();
});

test('a wrong code is rejected', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => '000000'])
        ->assertStatus(422);
});

test('a wrong code still costs an attempt', function () {
    // Counted before the comparison, so a crash mid-check cannot bypass the
    // cap.
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => '000000']);

    expect(OtpCode::firstOrFail()->attempts)->toBe(1);
});

test('a code dies after too many attempts', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    $code = sentCode();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => '000000']);
    }

    // Even the RIGHT code now fails — brute force must not be rewarded by
    // eventually guessing right.
    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => $code])
        ->assertStatus(422);
});

test('an expired code is rejected', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    $code = sentCode();

    OtpCode::query()->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => $code])
        ->assertStatus(422);
});

test('an unknown identifier gets the same response as a known one', function () {
    // Anything else turns this into an account-enumeration oracle, which is
    // the single most common way these flows leak.
    $known   = $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    $unknown = $this->postJson('/api/v1/otp/request', ['identifier' => 'nobody@example.com']);

    expect($known->json('message'))->toBe($unknown->json('message'))
        ->and($known->status())->toBe($unknown->status());
});

test('a correct code for a non-existent account still does not sign anyone in', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'nobody@example.com']);

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'nobody@example.com', 'code' => sentCode()])
        ->assertStatus(422);
});

test('the identifier is masked, never echoed in full', function () {
    // The confirmation screen is shown to whoever typed the address, who may
    // not be its owner.
    $response = $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);

    expect($response->json('masked'))->not->toBe('person@example.com')
        ->and($response->content())->not->toContain('person@example.com');
});

test('identifiers are matched case-insensitively', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'PERSON@Example.com']);

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => sentCode()])
        ->assertOk();
});

test('too many requests for one identifier is throttled', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com'])->assertOk();
    }

    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com'])
        ->assertStatus(429);
});

test('a successful verification clears the throttle', function () {
    // Making someone wait out a limit they triggered by mistyping punishes the
    // wrong behaviour.
    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    }

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => sentCode()])
        ->assertOk();

    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com'])->assertOk();
});

test('a non-email identifier is refused when no sms channel is bound', function () {
    // It fails cleanly rather than silently emailing a phone number into the
    // void.
    $this->postJson('/api/v1/otp/request', ['identifier' => '+15551234567'])
        ->assertStatus(422);
});

test('a project can bind its own channel', function () {
    // The whole point of the seam: no vendor is baked in.
    app()->bind('otp.channel.sms', fn () => new class implements Modules\Otp\Channels\OtpChannel
    {
        public static array $sent = [];

        public function send(string $identifier, string $code, string $purpose): void
        {
            self::$sent[] = [$identifier, $code];
        }

        public function supports(string $identifier): bool
        {
            return str_starts_with($identifier, '+');
        }

        public function mask(string $identifier): string
        {
            return '•••'.mb_substr($identifier, -4);
        }
    });

    $this->postJson('/api/v1/otp/request', ['identifier' => '+15551234567'])
        ->assertOk()
        ->assertJsonPath('masked', '•••4567');
});

test('the qa bypass works outside production', function () {
    // Every project without this grows a worse workaround — a hardcoded code
    // left in a branch, or a developer's phone number in a seeder.
    config()->set('otp.qa_bypass_code', '424242');

    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);

    $this->postJson('/api/v1/otp/verify', ['identifier' => 'person@example.com', 'code' => '424242'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('the qa bypass is refused in production even when configured', function () {
    config()->set('otp.qa_bypass_code', '424242');
    app()->detectEnvironment(fn () => 'production');

    expect($this->otp->bypassCode())->toBeNull()
        ->and($this->otp->matchesBypass('424242'))->toBeFalse();
});

test('the bypass is off when unset', function () {
    config()->set('otp.qa_bypass_code', null);

    expect($this->otp->bypassCode())->toBeNull();
});

test('codes are the configured length and numeric', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);

    expect(sentCode())->toMatch('/^\d{6}$/');
});

test('pruning removes long-expired codes and keeps live ones', function () {
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com']);
    OtpCode::create([
        'identifier' => 'old@example.com',
        'code_hash'  => Hash::make('111111'),
        'expires_at' => now()->subDays(30),
    ]);

    $this->artisan('otp:prune')->assertSuccessful();

    expect(OtpCode::count())->toBe(1);
});

test('the request endpoint validates its input', function () {
    $this->postJson('/api/v1/otp/request', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('identifier');
});

test('expires_at carries no ON UPDATE clause', function () {
    // Regression, and asserted against the SCHEMA rather than against behaviour
    // on purpose. `expires_at` was `$table->timestamp(...)` NOT NULL with no
    // default, and MySQL/MariaDB silently rewrite the first such column in a
    // table to `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` whenever
    // explicit_defaults_for_timestamp is off — which is the MariaDB default.
    //
    // The effect: verify() calls increment('attempts'), an UPDATE, and the
    // column is rewritten to now(). A code with ten minutes left has none the
    // moment somebody mistypes it once, so one typo permanently kills a
    // legitimate code. Confirmed directly against MariaDB before this was
    // written; it is not theoretical.
    //
    // Behavioural versions of this test are unreliable — the damage depends on
    // sub-second timing and on which connection the suite runs against — so the
    // property itself is pinned instead. It also catches the same mistake
    // reintroduced through any other column.
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Column metadata check is MySQL/MariaDB-specific.');
    }

    foreach (DB::select('SHOW COLUMNS FROM otp_codes') as $column) {
        expect(mb_strtolower((string) $column->Extra))
            ->not->toContain('on update', "otp_codes.{$column->Field} carries an ON UPDATE clause");
    }
});

test('the attempt cap is enforced on the write, not on the read', function () {
    // Read-then-increment let N parallel guesses all see attempts = 0, all pass
    // usable(), and all reach Hash::check — so the effective cap was the route
    // throttle (20/minute across the code's whole 10-minute life, ~200 guesses),
    // not the 5 configured here.
    //
    // A single-threaded test cannot provoke the race, so this pins the
    // MECHANISM: the cap must be a predicate on the UPDATE, where the database
    // adjudicates it, not a filter on a SELECT that every racer passes. Drop
    // the `where attempts <` and this goes red while every other test stays
    // green — which is exactly the bug, since usable() hides it from behaviour.
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com'])->assertOk();

    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    $this->postJson('/api/v1/otp/verify', [
        'identifier' => 'person@example.com',
        'code'       => '000000',
    ])->assertStatus(422);

    $increment = collect($statements)
        ->filter(fn (string $sql) => str_contains($sql, 'update') && str_contains($sql, 'otp_codes'))
        ->first(fn (string $sql) => str_contains($sql, 'attempts` = `attempts`'));

    expect($increment)->not->toBeNull('the attempt was never counted at all')
        ->and($increment)->toContain('`attempts` <');
});

test('consuming a code is a claim, so one code cannot mint two sessions', function () {
    // Two requests carrying the SAME correct code both passed Hash::check and
    // both wrote consumed_at — two tokens from one code. `whereNull` means
    // exactly one of them affects a row.
    //
    // Pinned as a predicate for the same reason as the cap: usable() already
    // filters consumed rows on the SELECT, so a sequential double-verify goes
    // green either way and proves nothing about two requests in flight at once.
    $this->postJson('/api/v1/otp/request', ['identifier' => 'person@example.com'])->assertOk();

    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    $this->postJson('/api/v1/otp/verify', [
        'identifier' => 'person@example.com',
        'code'       => sentCode(),
    ])->assertOk();

    $consume = collect($statements)
        ->first(fn (string $sql) => str_contains($sql, 'update') && str_contains($sql, 'consumed_at` = ?'));

    expect($consume)->not->toBeNull('the code was never consumed')
        ->and($consume)->toContain('`consumed_at` is null');
});
