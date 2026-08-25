# Notifications

Per-user database notifications: a paginated feed, an unread count, mark-read /
mark-all-read, dismissal, and the Vue half — a drop-in bell, a polling
composable, and a full notifications page.

## What it gives you

| Endpoint | Does |
|---|---|
| `GET /api/v1/notifications` | The signed-in user's feed, paginated |
| `GET /api/v1/notifications/unread-count` | Just the badge number |
| `POST /api/v1/notifications/read-all` | Marks the whole feed read |
| `POST /api/v1/notifications/{notification}/read` | Marks one read |
| `DELETE /api/v1/notifications/{notification}` | Dismisses one |

Every route is `auth:sanctum` and reads through the notifiable relation, so a
user only ever sees their own — there is no cross-user listing to gate.

## Install

```sh
php artisan module:add Notifications
php artisan migrate
```

No options, no composer requirements.

## Sending one

`Notifications/ExampleNotification.php` is there to copy. It is an ordinary
Laravel notification on the `database` channel — nothing about this module
changes how you send:

```php
$user->notify(new ExampleNotification('Your export is ready', route('exports.index')));
```

## Frontend

```vue
<!-- in your layout's app bar -->
<NotificationsBell />
```

| Piece | Use |
|---|---|
| `NotificationsBell.vue` | The drop-in. Owns fetching and polling. |
| `AppNotificationsBell.vue` | Presentational — props in, events out. |
| `useNotifications` | The composable underneath, if you want your own surface. |
| `NotificationsPage.vue` | The full feed, registered at `ROUTES.NOTIFICATIONS`. |

## Design decisions worth knowing

**The bell is two components, not one.** `AppNotificationsBell` takes props and
emits events and touches no API, so it can be previewed, tested and restyled
without a backend; `NotificationsBell` is the container that wires it to the
endpoints. Reach for the presentational one when a project wants a different
shape of bell — the fetching does not have to be rewritten to do it.

**Polling, not sockets.** `pollMs` defaults to `60_000` and hits only the
unread-count endpoint, which is a single indexed count rather than the feed.
Pass `0` to disable it if the project has real-time transport already.

**The feed is not the count.** They are separate endpoints on purpose: the badge
polls once a minute and the feed is fetched when someone opens it. Polling the
feed to render a number is the thing that makes a notification bell expensive.
