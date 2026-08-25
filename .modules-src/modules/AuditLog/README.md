# AuditLog

Field-level change history for any model. The kernel's `WhoDidIt` stamps *who*
last touched a row; this records *what* changed, from what, to what, by whom,
and optionally from where.

## Audit a model

```php
use Modules\AuditLog\Traits\Auditable;

class Item extends Model
{
    use Auditable;

    protected array $auditExclude = ['internal_notes'];   // optional
    protected array $auditOnly    = ['status', 'owner_id']; // optional allow-list
}
```

Created / updated / deleted / restored are recorded automatically. An update
whose only change is `updated_at` records nothing — that is noise, not history.

**Secrets are never captured.** `password`, `remember_token`, `api_token`,
`two_factor_*`, `access_token` and `refresh_token` are always dropped, as is
anything in the model's `$hidden`. The trail outlives the record, so it must
not become the place a secret leaks.

Entries are written even with no authenticated user — console commands, queued
jobs and seeders all change data, and dropping those would make the trail lie
by omission. Those rows have a null `user_id` and render as "system".

## Read it

```vue
<AuditTrail subject-type="App\Models\Item" :subject-id="item.id" />
```

`/audit-log` is the project-wide view, filterable by event.

## Who may read it

The module does **not** guess. Both endpoints call the `viewAuditLog` gate,
which your project defines — the trail can contain any field of any audited
model, so the visibility rule belongs to the project:

```php
Gate::define('viewAuditLog', fn (User $user) => $user->isAdmin());
```

Without that gate the endpoints return 403.

## Retention

Audit tables grow without bound and are usually the first to dominate a client
database. Schedule the prune:

```php
Schedule::command('audit:prune')->daily();
```

Window is `--days`, else `AUDIT_RETENTION_DAYS`, else 365. Deletes in batches so
one enormous transaction is never held open.

## Options

`capture=values` (default) records changed field values.
`capture=values+request` also records IP address and user agent, setting
`AUDIT_CAPTURE_REQUEST=true`.
