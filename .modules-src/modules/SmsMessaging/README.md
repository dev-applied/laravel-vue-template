# SmsMessaging

SMS as a first-class channel: a `sms` notification channel, a delivery log, and
the carrier-required STOP/START/HELP handling that every send is checked
against.

**Installs with no vendor account.** The default driver writes to the log, so
opt-out handling and the delivery log — most of the value — work before anyone
has signed up to anything.

## Sending

```php
app(\Modules\SmsMessaging\Support\SmsManager::class)
    ->send('+15551234567', 'Your order shipped.');
```

Or as a notification channel, alongside mail:

```php
public function via($notifiable): array { return ['mail', 'sms']; }
public function toSms($notifiable): string { return 'Your order shipped.'; }
```

The number comes from `routeNotificationForSms()` on the notifiable, falling
back to a `phone_number` attribute — the module never assumes a column name on
a model it does not own.

**Nothing should call a driver directly.** Everything that must happen on every
send lives in `SmsManager`, because a rule enforced per-driver is a rule that is
missing the day somebody adds the second driver:

1. **Normalise to E.164** so the opt-out list can match at all.
2. **Check the opt-out list.**
3. **Log the attempt** — including refusals, which are the ones support asks
   about.

## Opt-out is the point of the module

In the US, honouring STOP is a legal obligation under the TCPA and the CTIA
guidelines, not a courtesy, and a vendor will suspend a sender that ignores it.
That is the argument for it living in a module once rather than being
re-derived per client.

`POST /api/v1/sms/inbound` handles the keywords carriers deliver — STOP,
STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT, START, UNSTOP, YES, HELP, INFO — case
insensitively and with punctuation stripped, replying in TwiML.

STOP is answered with an **empty** document. The carrier sends its own
confirmation, and adding ours is both a duplicate and a message to somebody who
has just said stop sending messages.

Twilio can answer some keywords itself, depending on account settings. Relying
on that is the trap: the application's own opt-out list never learns, so the
number is suppressed at the vendor and still "sendable" as far as the app is
concerned — the exact state that produces a violation the day the vendor
changes.

An unparseable number counts as opted out. The alternative is sending to a
number the list could never have matched.

**Secure the webhook at the edge.** It has no auth, because the poster is a
carrier rather than a user. Restrict by vendor IP range or put it behind a
signed URL; the module cannot know which is right for a given deployment.

## The delivery log

`sms_messages` records every attempt with its status:

| status | meaning |
|---|---|
| `accepted` | the vendor took it — **not** that a handset received it |
| `suppressed` | refused because the recipient has opted out |
| `failed` | unusable number, missing credentials, or a vendor error |

`accepted` is deliberately not called `sent`. Carrier delivery is asynchronous
and arrives later on a status webhook, if at all; conflating the two is how a
"sent" column ends up meaning nothing.

**Bodies are stored**, because "what did we send them?" is unanswerable
otherwise. Redact anything secret before it reaches `send()` — an OTP code is
the obvious case, and the OTP bridge below sends the code in the body by
design, so treat the log as sensitive.

Reading it requires the `view-sms-log` ability, which **falls closed** when a
project has not defined it. Phone numbers plus message bodies is not something
to expose by default, and "any signed-in user" is not a boundary.

## With the Otp module

Otp ships email and declares `otp.channel.sms` as a binding a project provides
— it deliberately knows nothing about a vendor. Installing both modules binds
it automatically, so SMS codes work with no glue code, and every code goes
through `SmsManager` and therefore respects the opt-out list.

Template with `sms.otp_template` (`:code` is substituted).

## Options

| Option | Default | Effect |
|---|---|---|
| `driver` | `log` | `twilio` swaps in the REST driver and sets `TWILIO_*` |
| `inbound` | `webhook` | `none` drops the inbound controller, its route and its test |

The Twilio driver talks to the REST API rather than pulling the SDK: the SDK is
a large dependency tree for two endpoints, and HTTP means `Http::fake()` covers
it without a network or a vendor double.

## Tests

`SmsMessagingTest.php` — E.164 normalisation across nine input shapes, opt-out
matching whatever format was typed, suppression being recorded rather than
silently dropped, and the log falling closed.
`InboundSmsTest.php` — every STOP spelling, the empty-TwiML reply, START
undoing it, HELP naming the opt-out keyword, and an unusable `From` answering
200 rather than making the vendor retry forever.
`TwilioSmsSenderTest.php` — the posted payload, an error being returned rather
than thrown, and missing credentials failing without a request.
