<?php

declare(strict_types=1);

namespace Modules\Otp\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Where "this person proved they are present" is recorded.
 *
 * NOT the session. API routes have no session store, and a Capacitor client
 * authenticates with a bearer token and never has one at all — so a
 * session-backed step-up 500s on web and silently never works on mobile.
 *
 * Keyed by the ACCESS TOKEN rather than the user: stepping up on a laptop must
 * not also step up a phone that happens to be signed in as the same person.
 * That is the whole threat model — a session left open somewhere else.
 */
class StepUpStore
{
    public function mark(mixed $user): void
    {
        Cache::put($this->key($user), now()->timestamp, $this->window());
    }

    public function isVerified(mixed $user): bool
    {
        $at = Cache::get($this->key($user));

        return $at !== null && (now()->timestamp - (int) $at) <= $this->window();
    }

    public function clear(mixed $user): void
    {
        Cache::forget($this->key($user));
    }

    public function window(): int
    {
        return (int) config('otp.step_up_minutes', 15) * 60;
    }

    private function key(mixed $user): string
    {
        // Bearer auth gives a PersonalAccessToken with an id. Cookie/SPA auth
        // gives Sanctum's TransientToken, which has no key at all — so the
        // grain there is the browser session and the fallback is a constant.
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        $scope = $token !== null && method_exists($token, 'getKey')
            ? (string) $token->getKey()
            : 'session';

        return 'otp:step-up:'.$user->getKey().':'.$scope;
    }
}
