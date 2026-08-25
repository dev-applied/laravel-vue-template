# Dashboard

A dashboard **shell**, not a dashboard. It owns fetching, permission filtering,
caching, error isolation and layout. The project owns the numbers.

13 of 44 local client repos ship a landing dashboard, and every one of them was
hand-rolled: a controller stuffed with `Model::count()` calls, one request per
tile, and a chart library chosen at random. This module fixes the plumbing and
deliberately leaves the content — and the chart library — to the project.

## What it gives you

| Piece | What it does |
|---|---|
| `DashboardRegistry` | Where a project declares its widgets, from any service provider. |
| `GET /api/v1/dashboard` | **One** request for the whole dashboard, not one per tile. |
| Ability filtering | A widget the viewer can't see is omitted, not blanked. |
| Per-user caching | Opt-in, per widget, keyed by user so scoped tiles never cross-leak. |
| Error isolation | One failing widget renders its own error; the other seven still work. |
| `DashboardPage.vue` | KPI tile row, action queues, activity feeds, and a named `charts` slot. |

## Install

```sh
php artisan module:add Dashboard
php artisan migrate     # no migrations of its own — nothing to run
```

No options, no composer dependencies, **no charting library**.

## Registering widgets

Register from any service provider's `boot()`. The registry is a singleton.

```php
use Modules\Dashboard\Support\DashboardRegistry;

public function boot(DashboardRegistry $dashboard): void
{
    // A KPI tile.
    $dashboard->stat('open-orders', 'Open orders', fn () => [
        'value'   => Order::open()->count(),
        'change'  => 12,                       // % vs previous period; drives the arrow
        'caption' => 'vs last week',
        'url'     => '/orders?status=open',
    ], icon: 'mdi-package-variant', color: 'primary', cacheSeconds: 300);

    // A scoped tile — the resolver receives the viewing user.
    $dashboard->stat('my-tasks', 'My open tasks', fn ($user) => [
        'value' => $user->tasks()->open()->count(),
    ], icon: 'mdi-check-circle-outline');

    // Things needing attention.
    $dashboard->queue('needs-review', 'Needs review', fn () => [
        'total' => Submission::pending()->count(),
        'items' => Submission::pending()->latest()->take(5)->get()
            ->map(fn ($s) => [
                'id'       => $s->id,
                'title'    => $s->name,
                'subtitle' => $s->created_at->diffForHumans(),
                'url'      => "/submissions/{$s->id}",
                'badge'    => $s->priority,
                'color'    => $s->priority === 'high' ? 'error' : null,
            ]),
    ], ability: 'review-submissions');

    // A recent-activity feed.
    $dashboard->activity('recent', 'Recent activity', fn () => [
        'items' => AuditLog::latest()->take(10)->get()->map(fn ($log) => [
            'id'    => $log->id,
            'title' => $log->description,
            'icon'  => 'mdi-pencil',
            'at'    => $log->created_at->toIso8601String(),
        ]),
    ]);
}
```

`order` controls placement (stats default 100, queues 200, activity 300); ties
break on key so the layout is stable rather than hash-ordered.

## Design decisions worth knowing

**One request, not one per tile.** Eight widgets cost one round trip. That
difference is most of why hand-rolled dashboards feel slow. `?only[]=key`
narrows a refresh to a single tile, and the frontend patches it in place rather
than replacing the list.

**A widget you can't see is omitted, not blanked.** Returning an empty tile
still tells the viewer the tile exists. Pass `ability:` and the widget never
reaches the response.

**Caching is per user.** A scoped tile ("my open tasks") cached globally would
show one person another person's numbers. The cache key always includes the
viewer.

**One failing widget doesn't blank the dashboard.** The resolver is wrapped:
the failure is reported to your error tracker and the tile renders a neutral
message. The exception text never reaches the client — a leaked connection
string on a dashboard is a real incident.

**No charting library, on purpose.** No firm-standard chart library exists, and
shipping one guarantees either the wrong choice or two libraries in the bundle.
`DashboardPage.vue` exposes a named `charts` slot instead:

```vue
<DashboardPage>
  <template #charts="{widgets}">
    <MyChart :series="widgets.find(w => w.key === 'revenue')?.data" />
  </template>
</DashboardPage>
```

## Frontend

- `DashboardPage.vue` — the whole page. Props: `title`, `pollSeconds` (0 = off).
- `useDashboard()` — `widgets`, `stats`, `queues`, `activities`, `loading`,
  `loaded`, `widget(key)`, `fetch(only?)`. Use it directly to compose a
  bespoke layout instead of using the page.
- `AppStatTile` / `AppActionQueue` / `AppActivityFeed` — the three widget
  renderers, usable on their own.

Route constant: `ROUTES.DASHBOARD` → `/dashboard`.
