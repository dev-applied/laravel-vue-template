# FormBuilder

Forms a non-developer can define, rendered through the kernel's own field
components and validated server-side from the same definition.

Client-editable forms recur — intake, applications, surveys — and are usually
hardcoded, so every wording change is a deploy.

## What it gives you

| Piece | What it does |
|---|---|
| `forms` + `form_submissions` | Definitions and responses. |
| `SchemaValidator` | Refuses a broken definition before it reaches the public. |
| `FieldType` | The closed list of types and the rules each one implies. |
| `AppRenderedForm` | Renders a form anywhere. Registered globally. |
| `FormsPage` | Publish/unpublish, response counts. |

## Install

```sh
php artisan module:add FormBuilder
php artisan migrate
```

No options, no composer dependencies.

```php
Gate::define('manage-forms', fn ($user) => $user->isAdmin());
```

## Use it

```vue
<AppRenderedForm slug="volunteer-intake" @submitted="thanks" />
```

or point people at `/forms/volunteer-intake`, which needs no account when the
form is marked public.

A definition is a list of fields:

```json
[
  {"key": "full_name", "label": "Full name", "type": "text", "required": true},
  {"key": "email", "label": "Email", "type": "email", "required": true},
  {"key": "size", "label": "T-shirt size", "type": "select",
   "options": [{"value": "s", "label": "Small"}, {"value": "l", "label": "Large"}]},
  {"key": "agreed", "label": "I agree to the terms", "type": "checkbox", "required": true}
]
```

Types: `text`, `textarea`, `email`, `number`, `date`, `select`, `multiselect`,
`radio`, `checkbox`.

## Design decisions worth knowing

**The schema is snapshotted onto every submission.** Editing a form afterwards
must not rewrite the meaning of answers already collected. Without the
snapshot, relabelling "Full name" to "Legal name for the contract" makes
everyone who already answered appear to have answered the new question — the
worst failure mode a form builder has.

**Validation is built from the server's copy of the schema.** The client never
tells the server what the form contained; a client-supplied schema would let
anyone drop the `required` off a field or invent one.

**Only declared keys are stored.** Anything else the client sent is discarded.
A submissions export is read by people, and unexpected keys are how junk gets
in front of them.

**A choice field only accepts its declared choices.** Without that the dropdown
is a free-text field and the options are a suggestion.

**A broken definition is refused at save time.** A builder that lets someone
save a choice field with no options, or an unknown type, produces a form that
fails for the public and works fine for its author — the worst place to find
out. Unknown types are rejected rather than skipped: skipping renders nothing,
which means a required field nobody can see and a form that can never be
submitted.

**A missing key is generated; a duplicate key is rejected.** Two different
situations. A builder UI deriving keys from labels produces "email" twice the
moment someone adds a second "Email" field, so generated keys deduplicate —
and they are seeded with every explicit key first, so a generated one can never
collide with one the author wrote, even a later one. But an author who wrote
`key: email` twice meant something, and silently renaming one would break it.

**An unpublished form is a 404, not a 403.** Whether a draft exists at a
guessable slug is not public information.

**The render endpoint sends a projection, not the model.** A draft's internal
fields and its author are nobody else's business.

**Public submission is throttled.** It is an open write endpoint.

## Frontend

- `AppRenderedForm` — registered globally. Props: `slug`, `showTitle`. Emits
  `submitted`.
- `AppFormField` — one field, rendered with the kernel's field components, so a
  builder-made form looks and validates like every hand-built form in the app
  rather than being a second, worse set of inputs. Field names are
  `answers.<key>`, which is what `AppServerValidationForm` matches on.
- `useForm(slug)` — `form`, `fields`, `answers`, `loading`, `submitting`,
  `submitted`, `message`, `load()`, `submit()`.
- Routes: `ROUTES.FORM_FILL` → `/forms/:slug` (no auth pipeline — a public form
  has to work signed out), `ROUTES.FORMS` → `/admin/forms`.
