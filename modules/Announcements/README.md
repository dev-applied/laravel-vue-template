# Announcements

Scheduled in-app announcements, shown as a dismissible banner or a modal, with
per-user dismissal that survives a reload.

5 of 44 local client repos ship an announcement surface and every one is
hand-rolled — usually a hardcoded `<v-alert>` a developer edits and redeploys,
sometimes one backed by `localStorage`, which means "I dismissed this already"
lasts exactly until the person opens their phone.

## What it gives you

| Piece | What it does |
|---|---|
| Banner + modal | Two placements, four levels, an optional action button. |
| Scheduling | `starts_at` / `ends_at` windows evaluated at read time — no cron. |
| Per-user dismissal | A table row, not `localStorage`. Dismiss on the laptop, stay dismissed on the phone. |
| Acknowledgement | A required announcement is only cleared by an explicit "I understand", recorded with a timestamp. |
| `AudienceResolver` | Targeting the project defines. The module assumes no role system and no billing. |
| Authoring UI | List, filter by state, create/edit, publish/unpublish, dismissal counts. |

## Install

```sh
php artisan module:add Announcements
php artisan migrate
```

**Option — `delivery`:**

| Choice | What you get |
|---|---|
| `in-app` (default) | Banner and modal. Nothing leaves the app. |
| `in-app+email` | Publishing also queues an email to the resolved audience. |

## Wire it up

Two things a project must do.

**1. Drop the host in your layout.** The module registers it globally, so no
import is needed:

```vue
<!-- resources/ts/layouts/Default.vue -->
<AppAnnouncementsHost :poll-seconds="300" />
```

**2. Define the `manage-announcements` ability.** The authoring endpoints are
gated on it, and a project with no Gate definition denies by default — the
right way round for a surface that broadcasts to every user.

```php
Gate::define('manage-announcements', fn ($user) => $user->isAdmin());
```

## Targeting a subset

The default resolver understands exactly one audience, `everyone`, and matches
nothing else. To target a group, bind your own:

```php
use Modules\Announcements\Support\AudienceResolver;

class RoleAudience implements AudienceResolver
{
    public function matches(Announcement $announcement, mixed $user): bool
    {
        if ($announcement->audience === Announcement::AUDIENCE_EVERYONE) return $user !== null;

        return $user?->hasRole(Str::after($announcement->audience, 'role:')) ?? false;
    }

    public function audience(Announcement $announcement): iterable
    {
        // ... whoever should receive the email
    }
}

// A service provider's register():
$this->app->bind(AudienceResolver::class, RoleAudience::class);
```

Then an announcement with `audience = "role:editor"` reaches editors only.

## Design decisions worth knowing

**An unknown audience fails closed.** If the resolver does not recognise the
audience string, nobody sees it. The other direction — defaulting to "show it"
— broadcasts something meant for one group to the entire user base, and there
is no un-sending that.

**Dismissal is a row, not `localStorage`.** "It keeps coming back" is what
makes people stop reading announcements entirely.

**Dismissing twice writes one row.** `updateOrCreate` against a unique index,
because the second click on a slow button is the normal case. Two rows would
make the dismissal count the authoring UI shows wrong.

**Publishing twice does not re-send.** The publish endpoint is a no-op on an
already-published announcement, so a double-click cannot email everyone twice.

**The email job takes an id, not a model.** A serialized model in the queue
payload can be stale by the time a worker picks it up; an announcement
unpublished in those thirty seconds must not still go out. The job re-reads and
checks.

**One bad address does not abandon the send.** A per-recipient failure is
reported and the loop continues — a job-level retry would restart from the
first recipient and mail everyone twice.

**Windows are evaluated at read time.** No scheduled command has to run for an
announcement to appear or expire on time, which means nothing silently stops
working when a project's cron isn't wired up.

**Ordering is a portable CASE, not MySQL's `FIELD()`.** Errors sort above
warnings above info; the tests run on sqlite and `FIELD()` does not exist there.

## Frontend

- `AppAnnouncementsHost` — registered globally. Props: `pollSeconds` (0 = off).
- `AppAnnouncementBanner` / `AppAnnouncementDialog` — the two renderers.
- `useAnnouncements()` — `announcements`, `banners`, `modals`, `loading`,
  `fetch()`, `dismiss(a)`. Dismissal is optimistic; a failure brings the
  announcement back on the next fetch.
- `AnnouncementsPage.vue` + `AnnouncementFormDialog.vue` — the authoring UI.

Route constant: `ROUTES.ANNOUNCEMENTS` → `/announcements`.

## Sending exactly once (`in-app+email` only)

`announcement_deliveries` holds one row per address actually mailed, claimed
*before* the send and protected by a unique index on
`(announcement_id, recipient)`.

Two things used to mail the whole audience twice, and both stop here rather than
in application logic:

- **Publishing raced with itself.** The controller read `published_at`, found
  null, wrote it and queued the job. Two clicks arriving together both did that.
  The condition is now part of the UPDATE, so exactly one can win it.
- **The job's retry restarted at the first recipient.** It retries three times,
  so a worker killed near the end of a large send mailed almost everyone again.

Two consequences worth knowing about, both deliberate:

- **A send that throws does not release its claim.** The address may have been
  accepted upstream, so retrying risks a duplicate to fix a maybe-missed one.
  At-most-once is the right guarantee for a broadcast, and the recipient sees the
  announcement in-app regardless.
- **Unpublishing and re-publishing does not mail anyone a second time.** That
  flow exists to correct a mistake, not to request a re-send. To genuinely notify
  people again, create a new announcement.

The `in-app` option drops the table along with the job and the mailable — a
project that chose in-app-only should not carry an unused table.
