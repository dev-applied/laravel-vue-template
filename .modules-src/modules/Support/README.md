# Support

Inbound support, in two sizes.

| Option | What you get |
|---|---|
| `mode=contact` (default) | Public form that emails a mailbox. Submissions are still stored and searchable, but nothing is managed in-app. |
| `mode=ticketing` | The above plus statuses, priority, assignment, and threaded replies that email the requester. |

The contact choice drops the reply controller, its route, model, mailable,
view, both staff pages and the ticketing test file.

## Authorization

**Every staff route is gated on `manage-support`, which denies by default.**
The public submission endpoint is not, and must not be — that is the feature.

The staff routes previously carried `auth:sanctum` alone. Two consequences, and
the second is the sharper one:

- **Any authenticated user could read the entire ticket queue.** A support form
  is where customers paste passwords, order numbers and account details as a
  matter of routine, and `TicketResource` returns name, email and body.
- **`POST /support/tickets/{ticket}/replies` was an authenticated mail relay.**
  It sends an arbitrary 5000-character body to the ticket's email address, from
  our domain, with subject `Re: [REF] <their own subject>` — into a thread the
  customer already trusts. That is a phishing primitive with our sending
  reputation behind it, not merely a data leak.

```php
Gate::define('manage-support', fn (User $user) => $user->can('support.manage'));
```

Until it is defined, the queue is denied to everyone and the first attempt logs
a line pointing here. The public `POST /support/tickets` is unaffected.

## Spam handling

Three layers, none of which ever tells the sender they were filtered — a bot
told it failed just tries again differently:

- **Honeypot.** `website` is `prohibited`; it is off-screen rather than
  `type="hidden"`, which some bots skip.
- **Throttle.** 5 submissions per minute per IP.
- **Link heuristic.** Five or more URLs in the body flags `is_spam`. It FLAGS,
  never rejects, so a false positive is a row in the spam filter rather than a
  customer who could not reach you. Spam is hidden from the queue unless asked
  for, and `support:prune-spam` clears it out.

## Things that are deliberate

- The contact route is **not** behind Authentication. The person who cannot log
  in is exactly the person who needs to reach support.
- The notification email sets **reply-to the requester**, so hitting reply in
  the support mailbox answers the customer rather than yourself.
- **Internal notes are never emailed.** That is the worst bug this feature can
  have, so the guard is explicit and tested.
- Every ticket gets a short **reference** for people to quote.
- `routes.ts` registers the ticketing routes through `import.meta.glob`, not a
  static import: the file survives pruning but the pages do not, and a static
  lazy import of a dropped page fails the vite build.

## Config

`mail.support_address` is where submissions go; it falls back to
`mail.from.address`.
