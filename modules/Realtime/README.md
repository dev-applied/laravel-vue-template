# Realtime

Websocket wiring: broadcast auth behind Sanctum, a **served** client config, one
shared Echo connection, and a banner for when the socket drops.

**Ships no container.** The Reverb service is a docker-compose snippet below
rather than an automatic edit, because a module that rewrites your compose file
is a module you cannot install twice.

## The three things that are usually wrong

### 1. Broadcast auth on the `web` guard

Laravel's `Broadcast::routes()` defaults to `web`. An SPA on cookie auth works
either way; a **Capacitor build on a bearer token does not**, and the failure is
a socket that connects happily and then authorises no private channel at all —
no error, just nothing ever arriving.

This module registers `POST /api/v1/broadcasting/auth` behind `auth:sanctum`.

### 2. Config baked into the bundle

`import.meta.env.VITE_REVERB_HOST` is frozen at build time. A Capacitor app is
compiled once and pointed at an API afterwards, so that value is the wrong host
from then on — and the same build cannot serve staging and production.

`GET /api/v1/realtime/config` serves it instead. Everything in it is public: the
app **key** is not a secret, the **secret** is what signs the auth response and
never leaves the server. `enabled: false` lets a client say "realtime is not
configured here" rather than retrying a socket that will never open.

### 3. `REVERB_HOST` in a parallel worktree

Firm-standard knowledge, previously only in `~/.claude/stacks/laravel-vue.md`:

```
REVERB_HOST=<project>-<slug>-reverb-1   # the CONTAINER name, not the alias `reverb`
VITE_REVERB_HOST="${APP_DOMAIN}"
COMPOSE_PROJECT_NAME=<project>-<slug>   # set this BEFORE deriving REVERB_HOST
```

Both stacks alias `reverb` on the shared Traefik network, so DNS round-robins
and a worktree's PHP intermittently broadcasts into the **main stack's** Reverb.
The symptom is broadcasts that work about half the time.

`REVERB_HOST` (what PHP connects to, container-internal) and `VITE_REVERB_HOST`
(what the browser connects to, through Traefik) are different values. Setting
them the same is the other half of this.

## Declaring channels

```php
app(\Modules\Realtime\Support\ChannelGuards::class)->define(
    'order.{order}',
    fn (User $user, Order $order) => $user->can('view', $order),
);

app(\Modules\Realtime\Support\ChannelGuards::class)->presence(
    'order.{order}.editors',
    fn (User $user, Order $order) => ['id' => $user->id, 'name' => $user->name],
);
```

A channel with no guard is refused — Laravel's own default, and worth keeping.
The line that quietly undoes it is `Broadcast::channel('{anything}', fn () => true)`,
added to make a demo work, which opens every channel in the app.

`presence()` exists as its own method because the return types differ in a way
that is easy to get wrong by habit: `true` is a valid authorisation **and** an
empty member payload, so a presence channel guarded like a private one connects
fine and shows nobody. A presence guard returns the array other members see.

## Frontend

```ts
import {useRealtime} from "@modules/Realtime/resources/ts/composables/useRealtime"

const {connect, echo, state} = useRealtime()

await connect()
echo?.private(`order.${id}`).listen("OrderShipped", (e) => { /* … */ })
```

One shared connection at module scope. A per-component client opens a socket per
mounted component, and Reverb counts connections — a page with a handful of live
widgets starts getting refused.

`AppConnectionBanner.vue` shows when the socket has dropped, because that
failure is silent: a dead socket looks exactly like "nothing has happened yet",
so a user keeps reading a screen that stopped updating twenty minutes ago and
believes it. It stays hidden when realtime is merely *unconfigured* — that is
not broken, and telling every user about it is noise.

Call `disconnect()` on sign-out. A socket authorised as the previous user must
not survive them.

## The Reverb service

```yaml
  reverb:
    build: .
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    depends_on: [webserver]
    networks: [default, nginx-proxy]
    labels:
      - traefik.enable=true
      - traefik.http.routers.${DOCKER_ROUTER}-reverb.rule=Host(`${DOCKER_DOMAIN}`) && PathPrefix(`/app`)
      - traefik.http.services.${DOCKER_ROUTER}-reverb.loadbalancer.server.port=8080
```

## Options

| Option | Default | Effect |
|---|---|---|
| `banner` | `on` | `off` drops the banner; the composable still exposes the state |

## Tests

`RealtimeTest.php` — the config being public and honest about `enabled`, the
served host beating a baked one, auth refusing an anonymous request, an
unguarded channel being refused even to a signed-in user, a guarded channel
authorising only who it names, and the presence-versus-private return type.
