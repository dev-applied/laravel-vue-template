# Billing

Subscription entitlements resolved from RevenueCat webhooks, so **one** gate
answers for Apple, Google and web alike.

Built against the firm's `hybrid-billing` runbook — its decision register and
implementation contract — not from a client codebase. Every "worth knowing"
note below corresponds to a rule in that runbook, several of which exist
because they caused a real production incident.

> **This module deliberately does not integrate Stripe directly.** The firm's
> standing decision is a RevenueCat Billing (`rc_billing`) app with the
> processor connected as a gateway. A `stripe`-type app splits the catalog
> across Stripe's test and live modes and fails with error 8142 *after the
> paywall has rendered*, which reads as a frontend bug.

## What it gives you

| Piece | What it does |
|---|---|
| `POST /billing/webhook/revenuecat` | The only thing that grants access. |
| `GET /billing/entitlement` | What the client reads. It never writes. |
| `tier:` middleware | Server-side enforcement. |
| `Entitlements` service | Resolved access for a user, no User-model changes needed. |
| `AppPaywallGate` / `AppSubscriptionStatus` | The client half. |
| `billing:assert-safe` | Pre-deploy guard. Put it in the pipeline. |

## Install

```sh
php artisan module:add Billing
php artisan migrate
```

**Option — `admin`:**

| Choice | What you get |
|---|---|
| `none` (default) | No way to set a tier by hand. |
| `switcher` | An env-gated QA endpoint that reaches any entitlement state. |

## Environment

| Name | Kind | |
|---|---|---|
| `REVENUECAT_WEBHOOK_SECRET` | **Secret** | Backend only. Unset = every webhook rejected. |
| `REVENUECAT_SECRET_API_KEY` | **Secret** | V2 key. Needed to resolve transfers. |
| `BILLING_MANAGEMENT_URL` | Config | Hosted management URL for web subscribers. |
| `BILLING_ALLOW_SANDBOX` | Config | **Must be off in production.** |
| `BILLING_ALLOW_SWITCHER` | Config | **Must be off in production.** |

Add `php artisan billing:assert-safe --target=production` to the deploy
pipeline. A deploy that writes an env file from a stored variable silently
strips keys the variable predates, and the symptom is *silence* — no error, no
purchases. Failing the build is the only reliable defence.

## Use it

```php
// Server-side enforcement — the real gate.
Route::post('/reports/export', ...)->middleware('tier:premium');

// Anywhere else
app(Entitlements::class)->hasTier($user, 'premium');
```

Optionally add the trait for nicer call sites. The module works fully without
it, so a fresh `module:add` is never broken waiting on a manual edit:

```php
use Modules\Billing\Support\HasEntitlement;

class User extends Authenticatable
{
    use HasEntitlement;      // then: $user->hasTier('premium')
}
```

```vue
<AppPaywallGate tier="premium">
  <ExpensiveReport />
  <template #locked><UpgradePrompt /></template>
</AppPaywallGate>
```

After a purchase completes on the client:

```ts
const {pollForUpgrade} = useEntitlement()
await purchase()          // your RevenueCat SDK call
await pollForUpgrade()    // then re-read server state
```

## Design decisions worth knowing

**The webhook is the only thing that grants access.** A purchase call returning
"success" is a signal to *re-read server state*, never a fact about access. The
web purchase is client-driven and therefore forgeable.

**An unset webhook secret rejects everything.** This is the one place a
permissive default is catastrophic: an open webhook endpoint lets anyone grant
themselves any tier. Compared in constant time so it cannot be probed byte by
byte.

**Ignore-checks run BEFORE the ledger row is claimed.** This ordering is the
whole trap. Claim first and then decide to ignore, and that event id is
permanently burned — there is no resend button, so a later resend, or a retry
after you fix the condition that caused the ignore, is swallowed as a
duplicate. The ignored events are exactly the ones most worth replaying: one
with no attributable user, or a sandbox event received while sandbox granting
is off.

**A failed write releases the claim.** Otherwise the retry you just asked for
is rejected as a duplicate and the purchase is lost.

**Transfer is not an activation.** It looks like one — it lands naturally in
the granting set — but its payload carries no product id, no entitlement ids
and no expiry, so tier resolution finds nothing, falls through to `free`, and
wipes the account that just *received* the subscription. This demoted a real
paying customer. It gets its own branch, attributed by
`transferred_to`/`transferred_from` (`app_user_id` is null on these events),
and the subscriber is read back from the API rather than inferred.

**"Could not ask" is not "owns nothing".** Reading a subscriber back has three
outcomes, not two. Collapsing an API failure into "owns nothing" turns a vendor
outage into mass revocation of paying customers, so an unresolvable read leaves
state alone and lets the retry settle it. A wrong write is silent; a delayed
one is not.

**Cancellation keeps access.** It means auto-renew off, not loss of access.
Downgrading there robs a customer of time they already paid for.

**A billing issue changes nothing.** The store runs its own grace period and
sends an expiration if it truly lapses. Revoking cuts off a customer whose card
retry is about to succeed.

**Revocation is one shared patch, used on every path.** A revocation that
clears the tier but forgets an expiry column leaves a ghost entitlement some
other code path will honour.

**An unrecognised event type is inert.** Vendors add types over time; a new one
must never corrupt a paying customer's state.

**The entitlement identifier beats the product SKU, in order.** Entitlements
are what you configured to mean "has premium"; SKUs are free-form store strings
a client will rename. Concatenating both and searching the result is *not*
precedence — a legacy SKU like `legacy_premium_bundle_v2` would silently
upgrade a Basic holder. The tests catch exactly that.

**Expiries are milliseconds.** Passing RevenueCat's value to Carbon as seconds
lands the expiry in the year 57000 and the subscription never expires.

**`trial_used` is sticky.** It is the only thing separating "never subscribed"
from "trial expired", which need different copy and different walls.

**The clock is a backstop for a missed webhook.** A row whose period end has
passed stops granting even if no expiration event ever arrived.

**Management routing follows the processor, not the device.** Someone who
bought on iOS still manages through Apple while sitting on the web. Apple and
Google get copy rather than a link where policy forbids one — pointing an iOS
subscriber at web billing is external-purchase steering and gets the app
rejected.

**A refused request is 402, not 403.** So the frontend opens the paywall rather
than treating it as a permissions problem or logging the person out.

**The paywall gate renders nothing until the entitlement has loaded.** Showing
the locked state first makes a paying customer see the paywall flash on every
page load.

**The purchase-to-webhook race is polled, then reconciled on return.** The
purchase call returns before the webhook has written. Refreshing once leaves a
paying customer looking at the paywall they just paid to dismiss — the
highest-severity bug in this domain, because it reads as "I paid and got
nothing".

## The QA switcher

Reaching "trial expired, card declined, previously premium" through real store
purchases is impractical, and a project without a switcher grows a worse
workaround — a tester given production admin, or a hardcoded branch that ships.

It follows the same safety rules as the sandbox flag: env-gated and off by
default, **refuses outright in production whatever the env says**, acts only on
the caller's own entitlement, marks the provider `manual` so nothing mistakes
it for a real purchase, and logs every use at warning level.

## Frontend

- `AppPaywallGate` — `tier` prop, default and `locked` slots. Registered
  globally.
- `AppSubscriptionStatus` — plan, renewal date, and the correct management
  route or copy.
- `useEntitlement()` — `entitlement`, `isActive`, `loading`, `loaded`,
  `hasTier(tier)`, `refresh()`, `pollForUpgrade()`, `reconcileOnReturn()`. A
  failed read **fails closed** — it must never look like a subscription.
