# Otp

One-time codes for passwordless sign-in and step-up verification.

Ships an **email** channel and nothing else. SMS is a contract a project binds
— which is the point: Twilio appears in ten local repos and every OTP flow
that hard-coupled to it had to be forked to run anywhere else.

## What it gives you

| Piece | What it does |
|---|---|
| `POST /otp/request` + `/otp/verify` | Passwordless sign-in, returning a Sanctum token. |
| `OtpChannel` | The delivery contract. Bind `otp.channel.sms` for SMS. |
| `RequiresStepUp` middleware | Re-verify before a sensitive action. |
| `AppOtpInput` | A code field with `autocomplete="one-time-code"`. |
| `otp:prune` | Deletes expired codes. |
| QA bypass | Env-gated, non-production only. |

## Install

```sh
php artisan module:add Otp
php artisan migrate
```

**Option — `purpose`:**

| Choice | What you get |
|---|---|
| `login` (default) | Sign-in codes only. |
| `login+step-up` | Also re-verification before sensitive actions. |

## Adding SMS

```php
use Modules\Otp\Channels\OtpChannel;

class TwilioOtpChannel implements OtpChannel
{
    public function send(string $identifier, string $code, string $purpose): void
    {
        // your vendor here
    }

    public function supports(string $identifier): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $identifier);
    }

    public function mask(string $identifier): string
    {
        return '•••'.substr($identifier, -4);
    }
}

// A service provider's register():
$this->app->bind('otp.channel.sms', TwilioOtpChannel::class);
```

The channel is chosen from the shape of the identifier: an email address goes
to email, anything else is assumed to be a phone number. With no SMS channel
bound, a phone number fails cleanly with a 422 rather than being emailed into
the void.

## Step-up verification

```php
Route::delete('/account', ...)->middleware('otp.step-up');
```

Unverified requests get **428**, not 401. A bare 401 makes the frontend log the
person out instead of opening the step-up dialog.

## Config

Everything is env-driven with sane defaults:

| Env | Default | |
|---|---|---|
| `OTP_TTL_MINUTES` | 10 | Code lifetime |
| `OTP_LENGTH` | 6 | Digits |
| `OTP_MAX_ATTEMPTS` | 5 | Wrong guesses before the code dies |
| `OTP_MAX_PER_HOUR` | 5 | Requests per identifier |
| `OTP_MAX_PER_HOUR_PER_IP` | 20 | Requests per IP |
| `OTP_STEP_UP_MINUTES` | 15 | How long a step-up counts for |
| `OTP_QA_BYPASS_CODE` | *(unset)* | See below |

## The QA bypass

Automated tests and manual QA cannot read a real inbox, and every project
lacking a bypass grows a worse workaround — a hardcoded code left in a branch,
or a developer's phone number in a seeder.

```env
OTP_QA_BYPASS_CODE=424242
```

Gated on the env value **and** a non-production environment, both. Setting it
in production does nothing: `OtpManager` refuses it there regardless, so a
leaked `.env` line cannot become an authentication bypass.

## Design decisions worth knowing

**The response is identical whether or not the account exists.** Anything else
turns `/otp/request` into an account-enumeration oracle, which is the single
most common way these flows leak. Even a *correct* code for an address with no
account returns the same 422 as a wrong one.

**Codes are hashed at rest.** A database read should not hand someone a working
credential, and a code is a credential for the seconds it lives.

**Requesting a new code retires the old one.** Two live codes means the older
keeps working after the person asked for a new one — exactly the window an
attacker wants.

**The attempt is counted before the comparison.** A crash or a timeout
mid-check still costs an attempt; otherwise the cap is bypassable.

**After the cap, even the right code fails.** Brute force must not be rewarded
by eventually guessing right.

**Rate limited per identifier AND per IP.** Per identifier stops one person
being spammed with codes; per IP stops one machine enumerating many
identifiers. Either alone leaves the other attack open.

**A successful verification clears the throttle.** Making someone wait out a
limit they triggered by mistyping punishes the wrong behaviour.

**`random_int`, not `rand`.** A predictable generator makes the whole flow
theatre.

**The identifier is masked in every response.** The confirmation screen is
shown to whoever typed the address, who may not be its owner.

**Step-up state lives in the cache keyed by access token — not the session.**
API routes have no session store, so a session-backed step-up 500s on web; a
Capacitor client authenticates with a bearer token and never has a session at
all, so it would silently never work. Keying by token also means stepping up on
a laptop does not step up a phone signed in as the same person — which is the
whole threat model, a session left open somewhere else.

**A login code does not satisfy a step-up.** Different purposes are different
codes; otherwise the code emailed to sign in also authorises deleting the
account.

## Frontend

- `OtpLoginPage.vue` — the two-step sign-in screen. Route `ROUTES.OTP_LOGIN` →
  `/sign-in/code`.
- `AppOtpInput` — `inputmode="numeric"` and `autocomplete="one-time-code"`, so
  phones open the number pad and offer the code straight from the message. The
  single biggest usability win here, and the one every hand-rolled version
  misses. Emits `complete` on the last digit so there is no extra tap.
- `useOtp()` — `identifier`, `code`, `masked`, `sent`, `sending`, `verifying`,
  `secondsLeft`, `canResend`, `request()`, `verify()`, `reset()`. A wrong code
  clears the field but keeps the screen, so retrying does not mean requesting a
  new code.
