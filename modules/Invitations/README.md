# Invitations

Invite people by email; they create their own account from a tokenised link.

## Authorization

**Managing invitations is gated on `manage-invitations`, which denies by default.**

The management routes previously carried `auth:sanctum` and nothing else, while
`InvitationController`'s own docblock claimed an `invitations.manage` permission
that did not exist anywhere in the codebase. Combined with a `role` field
validated only as `string|max:125`, that made this module a privilege-escalation
ladder:

1. Any authenticated user posts `{"email": "me+2@theirdomain.com", "role": "super-admin"}`.
2. The invitation arrives in their own mailbox.
3. They accept it, and `AcceptInvitationController` calls
   `assignRole('super-admin')` on the fresh account.

Define the gate in `AppServiceProvider::boot()`:

```php
Gate::define('manage-invitations', fn (User $user) => $user->can('invitations.manage'));
```

`role` now carries two separate controls:

- **It must name a role that EXISTS.** With no `roles` table there is no spatie
  install, so `assignRole` does not exist and the value is inert *today* — but it
  is stored, and it goes live the day the project adds RolesPermissions. A
  stored role nobody validated is a landmine, so it is refused outright rather
  than kept.
- **The inviter must already hold the role they are handing out**, unless they
  pass `assign-any-role`. Without this, "may invite people" quietly means "may
  create a super-admin", and the route gate is not the boundary it appears to
  be — an invitation manager could promote themselves through a second account.

```php
// Let an owner hand out anything; everyone else may only pass on what they hold.
Gate::define('assign-any-role', fn (User $user) => $user->hasRole('owner'));
```

## Flow

1. An admin posts an email (optionally a role) to `/api/v1/invitations`.
2. The invitee gets a link to `/accept-invite?token=…`.
3. They set a name and password. **The account is created at this point**, not
   when the invite was sent — creating it up front leaves dormant rows that can
   be enumerated and password-reset into, and makes "invited" and "registered"
   indistinguishable.
4. They are returned a bearer token and land signed in.

## Security properties (each has a test)

- **Tokens are hashed at rest.** Only `sha256(token)` is stored, and the column
  is `$hidden`. A leaked database does not hand the reader working invite links.
- **Single use.** Accepting stamps `accepted_at`; a second submission 404s. The
  accept path re-checks under `lockForUpdate()`, so two simultaneous submissions
  of the same link cannot both create an account.
- **Expiring.** Default 7 days.
- **Resend re-issues.** A new token is minted and the previous link stops
  working immediately, so a forwarded old email cannot be used.
- **One live invitation per email.** Issuing revokes any outstanding one.
- **No membership oracle.** Inviting an address that already has an account
  returns the same `sent: true` shape and sends nothing. Unknown, expired,
  revoked and already-accepted tokens all return one identical message.
- **Revoke, don't delete.** The record stays so "who was invited and then
  un-invited" remains answerable.
- Public endpoints are throttled (10/min).

## Roles

If an invitation carries a `role` and the user model can `assignRole()` (the
RolesPermissions module), it is applied on accept. Without that module the field
is simply recorded — a module may not assume another module is installed.

## Retention

```php
Schedule::command('invitations:prune')->daily();
```

Deletes accepted, revoked and expired invitations older than `--days` (30).
Pending ones are never pruned.

## Frontend

- `/invitations` — admin listing with send, resend and revoke.
- `/accept-invite` — public (Guest middleware), previews the token before
  rendering the form so a dead link says so before anyone types a password.
