# Comments

Comments and internal notes attachable to any model, with @mentions.

Notes-on-a-record recurs across client repos and is re-implemented per model
every time — an `order_notes` table here, a `notes` text column there, and no
two of them behaving the same.

## What it gives you

| Piece | What it does |
|---|---|
| `HasComments` trait | Add to any model. |
| `CommentableRegistry` | The allow-list of what accepts comments, plus the ability to check. |
| Internal notes | Staff-only, hidden by default from everyone. |
| @mentions | An explicit token, recorded on the comment, firing an event. |
| `UserMentioned` event | The seam. This module never assumes a notification system. |
| `AppComments` | The whole surface — list, composer, mention picker. Registered globally. |

## Install

```sh
php artisan module:add Comments
php artisan migrate
```

**Option — `threading`:**

| Choice | What you get |
|---|---|
| `flat` (default) | One list, oldest first. Simpler to read and to moderate. |
| `threaded` | One level of replies under each comment. |

## Wire it up

**1. Trait the model.**

```php
use Modules\Comments\Traits\HasComments;

class Order extends Model
{
    use HasComments;
}
```

**2. Register it.** The endpoint takes a type from the request, so without an
allow-list a caller could attach a comment to any Eloquent class in the app —
including ones whose ids they should not be able to confirm exist.

```php
// A service provider's boot():
$registry->register('order', Order::class, ability: 'view');
```

The ability runs against the **resolved record**, so a project can allow
comments on records a person owns and refuse the rest.

**3. Drop the component.**

```vue
<AppComments
  type="order"
  :record-id="order.id"
  :mentionable="teammates"
  :allow-internal="$auth.hasPermission('see-internal-comments')"
  threaded
/>
```

**4. Optionally, define who sees internal notes.**

```php
Gate::define('see-internal-comments', fn ($user) => $user->isStaff());
```

Undefined means nobody — internal notes stay invisible until a project
deliberately opens the door.

## Mentions

The stored token is `@[Display Name](user:12)`. The id is explicit rather than
resolved from the text: matching on `@devin` would need a unique username
column this module cannot assume exists, and would silently mention the wrong
person whenever two people share a first name.

Mentioning fires `UserMentioned` — an event, not a notification:

```php
Event::listen(UserMentioned::class, function (UserMentioned $event) {
    $event->user->notify(new MentionedInComment($event->comment));
});
```

That is deliberate. This module must not assume the Notifications module is
installed, or that a project wants a database notification rather than an
email or a Slack post. It reports the fact; the project decides what happens.

## Design decisions worth knowing

**Editing does not re-notify.** The event fires once per *newly* mentioned
person. Re-notifying everyone when someone fixes a typo is the behaviour that
trains people to ignore mentions entirely.

**A mention of a user who no longer exists is dropped, not fatal.** Syncing a
stale id straight to the pivot hits the foreign key and 500s the request —
losing what the person wrote over a bad mention.

**Posting an internal note needs the same ability as reading one.** Otherwise
anyone could file a note they cannot then see, and staff would be reading input
from someone never meant to write there.

**Nesting stops at one level.** Arbitrary depth produces threads nobody can
read and a recursive query nobody can index. A reply-to-a-reply is flattened
to a reply rather than rejected — the intent was clearly "comment on this".

**A reply pointing at another record's comment becomes a root comment.**
Otherwise it renders nowhere at all.

**Deleting a comment takes its replies.** A reply to nothing reads as a
non-sequitur.

**A deleted author leaves the comment readable.** `nullOnDelete` plus a
"Deleted user" label — the thread still has to make sense without the account.
Note this is the one place `whenLoaded` is wrong: Laravel returns
`MissingValue` for a loaded-but-null relation, which would drop the author key
entirely.

**Edits are stamped.** A comment that silently changed after someone replied to
it is worse than one that says it changed.

## Frontend

- `AppComments` — registered globally. Props: `type`, `recordId`,
  `mentionable`, `allowInternal`, `threaded`, `title`.
- `AppCommentComposer` — textarea with the `@` picker. Emits
  `submit({body, isInternal})`.
- `AppCommentItem` — one comment and its replies. Uses `v-show` for the edit
  state so switching does not drop focus mid-edit.
- `useComments(type, id)` — `comments`, `count`, `loading`, `loaded`,
  `fetch()`, `post(body, opts)`, `edit(comment, body)`, `remove(comment)`.
- `mentionToken(user)` / `toPlainText(body)` — the token helpers.
