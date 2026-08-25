# Checklists

Reusable checklist templates, instantiated against any registered model.

## The shape worth extracting

Two production apps arrived at the same **template / instance** split
independently, and it is the whole reason this is a module rather than a table.

A template is edited over time — somebody adds a step, rewords another, deletes
a third. An instance must **not** change under the person who filled it in, so
`Checklist::instantiate()` **copies** the template's items rather than
referencing them. A checklist signed last month has to keep saying what was
actually checked, which is the entire reason anybody keeps one.

Deleting a template does not delete the inspections done under it: the FK is
`nullOnDelete`, and the instance already carries its own copy of the name and
every line.

## Checklists vs FormBuilder

They are **siblings, not a preset of one another** — decide once and stop
re-deciding:

| | FormBuilder | Checklists |
|---|---|---|
| Filled | once, by whoever the form is for | repeatedly, against a subject |
| Attached to | nothing | any registered model |
| Answers | arbitrary field types | pass / fail / n-a, note, photo |
| Point of it | collect data you do not have | record that something was verified |

If you need "did somebody check X on this vehicle, and can we prove it in a
year", this is the one.

## Registering what can be inspected

```php
app(\Modules\Checklists\Support\ChecklistSubjects::class)
    ->register('vehicle', Vehicle::class);
```

An allow-list, for the same reason Exports and GlobalSearch use one: the
instantiate endpoint takes a subject type off the wire, and without a registry
that is an arbitrary-model-lookup endpoint. **The key travels, never the class
name** — a client sending `App\Models\User` and having it resolved is how an
allow-list turns back into the thing it was meant to replace.

## Endpoints

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/v1/checklist-templates` | Active templates and their items |
| `POST` | `/api/v1/checklist-templates` | Gated on `manage-checklist-templates` |
| `POST` | `/api/v1/checklist-templates/{t}/archive` | Archive, never delete |
| `GET` | `/api/v1/checklists?subject_type=&subject_id=` | Instances against one subject |
| `POST` | `/api/v1/checklists` | Start one from a template |
| `PATCH` | `/api/v1/checklists/{c}/responses/{r}` | Answer a line |
| `POST` | `/api/v1/checklists/{c}/complete` | Sign it off |

Editing templates is gated; **filling one in is not**. Who may change what gets
inspected is a different question from who carries out the inspection, and
answering it with "any signed-in user" is how a compliance checklist stops
meaning anything. `manage-checklist-templates` falls closed.

## Rules that are rules

- **`pending` is not an answer** a caller may set. It is the absence of one, and
  accepting it would quietly un-answer a line somebody had already signed off.
- **A completed checklist cannot be edited.** It is a record; editing after
  sign-off means the signature no longer describes what it signed.
- **Completion reports every reason at once**, not the first. A checklist that
  reveals one missing item per attempt is the reason people stop using them.
- **Evidence is demanded of the ANSWER, not the item.** A step marked
  not-applicable cannot have a photo of itself, and demanding one is how a
  checklist becomes unfinishable.
- **An optional item left pending does not block completion.** That is what
  optional means.

## Photo evidence (`evidence=files`)

An item can set `requires_evidence`, and a `pass` on it then needs a `file_id`.

`file_id` is **not** a foreign key to the Files module's table. Checklists
installs without Files, and a constraint on a table that may not exist is a
migration that fails on half the projects that want this module. The frontend
component reaches for `AppFileUpload` through `import.meta.glob` and says so
plainly when Files is absent.

## Options

| Option | Default | Effect |
|---|---|---|
| `evidence` | `files` | `none` drops the evidence component and its test |

## Tests

`ChecklistsTest.php` — the copy surviving a template edit and a template
deletion, every completion rule above, the subject allow-list refusing a class
name, an archived template refusing to start, and the template gate falling
closed. `ChecklistEvidenceTest.php` — a file satisfying the requirement, and a
pass without one still blocking.
