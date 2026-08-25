# Onboarding

A first-run checklist. A project declares its setup steps; the module tracks
each user's progress, notices steps already satisfied elsewhere in the app, and
optionally refuses gated routes until the required ones are done.

## Declaring steps

From `AppServiceProvider::boot()`:

```php
app(\Modules\Onboarding\Support\OnboardingRegistry::class)->register(
    key:           'profile',
    label:         'Complete your profile',
    description:   'Add a name and a photo so colleagues recognise you.',
    route:         ['name' => 'profile.edit'],
    icon:          'account_circle',
    required:      true,
    completedWhen: fn (User $user) => filled($user->avatar_path),
    order:         0,
);
```

The registry is the whole configuration surface — no config file, no seeded
rows. Delete a step from the provider and it is gone; its progress rows go
inert rather than resurfacing a step nobody declares any more.

### `completedWhen` is the part that matters

A step is usually already satisfied by something the user did elsewhere: they
uploaded an avatar on the profile screen, they invited a colleague from the team
page. Asking them to come back and tick a box for work they have already done is
what makes onboarding feel like paperwork. Given the closure, the step reports
itself complete with nothing written to the database.

A step with no `completedWhen` is completed only by an explicit POST.

### required vs optional

`required: true` steps hold the gate shut and are the ones counted in
`outstandingRequired`. They **cannot be skipped** — a skippable required step
makes "required" a label rather than a rule, and the gate would then pass users
who clicked past the thing it exists to insist on.

Optional steps can be skipped individually or all at once. Skipping is not
final: doing a skipped step later shows it as **done**, not passed over. That
is the ordinary path — skip "invite your team" on day one, invite somebody in
week two.

## Endpoints

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/v1/onboarding` | Declared steps + this user's position in them |
| `POST` | `/api/v1/onboarding/{step}/complete` | 404 on an unknown step |
| `POST` | `/api/v1/onboarding/{step}/skip` | 422 if the step is required |
| `POST` | `/api/v1/onboarding/skip` | Skip every optional step |

Every mutating call returns the **recomputed state**, so the client never has to
re-derive progress locally and cannot drift from the server.

These endpoints are never behind the `onboarded` middleware. They are what
somebody uses to *become* onboarded, and gating them locks the user out of the
only screen that would release them.

## The gate (`gate=middleware`)

Registers an `onboarded` middleware alias. Apply it **per route**, in your own
route files:

```php
Route::middleware(['auth:sanctum', 'onboarded'])->group(function () {
    // …everything a half-configured account should not reach
});
```

Never register it globally. The onboarding endpoints, the auth endpoints and
whatever screens the steps link to all have to stay reachable, so a global gate
needs exceptions it cannot know — and the failure mode is a signed-in user who
can reach nothing at all.

It answers `403` with a machine-readable body rather than redirecting, because
this is an API and the SPA router decides where to send someone:

```json
{
  "message": "Finish setting up your account first.",
  "onboarding": {"complete": false, "nextStep": "profile", "outstandingRequired": 1}
}
```

An outstanding *optional* step does not hold the gate shut. That is the whole
meaning of optional.

## Frontend

- `pages/OnboardingPage.vue` — the checklist, at `/onboarding`. Routed behind
  `Authentication` only, for the lock-out reason above.
- `components/AppOnboardingBanner.vue` (`banner=on`) — a persistent bar for any
  layout. Dismissal is per session, not persisted: a permanently dismissible
  reminder of unfinished *required* setup is a hidden one.
- `composables/useOnboarding.ts` — one shared state at module scope, so the
  banner and the page read the same numbers. Completing a step on the page
  updates the banner without a reload, because every mutating call applies the
  server's recomputed state rather than adjusting a local copy.

## Options

| Option | Default | Effect |
|---|---|---|
| `gate` | `middleware` | `none` drops the middleware and its test |
| `banner` | `on` | `off` drops the banner component |

## Tests

`OnboardingTest.php` covers ordering, `completedWhen` writing nothing,
required-cannot-be-skipped, skip-then-complete precedence, skip-all leaving
required steps alone, unknown steps, and per-user isolation.
`RequireOnboardingTest.php` covers the gate closing and opening, optional steps
not holding it shut, the onboarding endpoints never being gated, and the gate
deferring to auth rather than answering for it.
