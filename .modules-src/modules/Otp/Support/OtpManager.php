<?php

declare(strict_types=1);

namespace Modules\Otp\Support;

use App\Exceptions\AppException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\Otp\Models\OtpCode;

/**
 * Issues and verifies one-time codes.
 *
 * The whole surface is a credential handling path, so the defaults are the
 * strict ones: hashed at rest, single use, attempt-capped, rate limited per
 * identifier AND per IP, and identical responses whether or not the identifier
 * exists.
 */
class OtpManager
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * @return array{masked: string, expires_in: int}
     */
    public function issue(string $identifier, string $purpose = 'login', ?string $channelName = null, ?string $ip = null): array
    {
        $channelName ??= $this->channels->guess($identifier);
        $channel = $this->channels->get($channelName);

        if (! $channel->supports($identifier)) {
            throw new AppException('That does not look like a valid destination.', 422);
        }

        $this->assertNotThrottled($identifier, $ip);

        // Any outstanding code for this identifier and purpose is retired.
        // Two live codes means the older one keeps working after the person
        // asked for a new one, which is exactly the window an attacker wants.
        OtpCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generate();

        OtpCode::create([
            'identifier' => $identifier,
            'channel'    => $channelName,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes', 10)),
            'ip_address' => $ip,
        ]);

        $channel->send($identifier, $code, $purpose);

        return [
            'masked'     => $channel->mask($identifier),
            'expires_in' => (int) config('otp.ttl_minutes', 10) * 60,
        ];
    }

    /**
     * True when the code is correct, unexpired and unused. Consumes it.
     */
    public function verify(string $identifier, string $code, string $purpose = 'login'): bool
    {
        $record = OtpCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->usable()
            ->latest('id')
            ->first();

        if ($record === null) {
            return false;
        }

        // Counted before the comparison, so a crash or a timeout mid-check
        // still costs an attempt — otherwise the cap is bypassable.
        //
        // The condition is on the UPDATE, not on the SELECT above, and that is
        // the whole point. Read-then-increment let N parallel requests all see
        // `attempts = 0`, all pass `usable()`, and all reach Hash::check — so
        // the real cap was the route throttle (20/minute for the code's whole
        // life, ~200 guesses) rather than the 5 this is supposed to enforce.
        // A conditional increment returns 0 affected rows once the cap is
        // reached, whichever request gets there first.
        $counted = OtpCode::query()
            ->whereKey($record->getKey())
            ->where('attempts', '<', (int) config('otp.max_attempts', 5))
            ->increment('attempts');

        if ($counted === 0) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            return false;
        }

        // Consumption is a claim, not a write. Two requests carrying the SAME
        // correct code both passed Hash::check and both wrote consumed_at,
        // minting two sessions from one code. `whereNull` means exactly one of
        // them affects a row; the loser is told no.
        $consumed = OtpCode::query()
            ->whereKey($record->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($consumed === 0) {
            return false;
        }

        // A successful verification clears the throttle: the person proved
        // they are who they said, and making them wait out a rate limit they
        // triggered by mistyping is punishing the wrong behaviour.
        RateLimiter::clear($this->key('id', $identifier));

        return true;
    }

    /**
     * The env-gated QA bypass.
     *
     * Automated tests and manual QA cannot read a real inbox, and every
     * project that lacks this grows a worse workaround — a hardcoded code left
     * in a branch, or a developer's phone number in a seeder. Gated on an
     * explicit env value AND a non-production environment, both.
     */
    public function bypassCode(): ?string
    {
        $code = config('otp.qa_bypass_code');

        if (! $code || app()->environment('production')) {
            return null;
        }

        return (string) $code;
    }

    public function matchesBypass(string $code): bool
    {
        $bypass = $this->bypassCode();

        return $bypass !== null && hash_equals($bypass, $code);
    }

    private function generate(): string
    {
        $length = max(4, min(10, (int) config('otp.length', 6)));

        // random_int, not rand/mt_rand: this is a credential, and a predictable
        // generator makes the whole flow theatre.
        return mb_str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function assertNotThrottled(string $identifier, ?string $ip): void
    {
        $perIdentifier = (int) config('otp.max_per_hour', 5);
        $perIp         = (int) config('otp.max_per_hour_per_ip', 20);

        // Per identifier stops someone being spammed with codes. Per IP stops
        // one machine enumerating many identifiers. Both are needed; either
        // alone leaves the other attack open.
        if (RateLimiter::tooManyAttempts($this->key('id', $identifier), $perIdentifier)) {
            throw new AppException('Too many codes requested. Try again shortly.', 429);
        }

        if ($ip !== null && RateLimiter::tooManyAttempts($this->key('ip', $ip), $perIp)) {
            throw new AppException('Too many codes requested. Try again shortly.', 429);
        }

        RateLimiter::hit($this->key('id', $identifier), 3600);

        if ($ip !== null) {
            RateLimiter::hit($this->key('ip', $ip), 3600);
        }
    }

    private function key(string $kind, string $value): string
    {
        return 'otp:'.$kind.':'.Str::lower(hash('xxh128', $value));
    }
}
