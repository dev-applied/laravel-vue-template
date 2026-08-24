## Authentication

> **Auth is a module.** A bare template has no login. Add it with
> `php artisan module:add Auth` (or via `project:init`) and choose **Sanctum**
> (bearer PAT for the MCP endpoint) or **Sanctum + Passport OAuth** (adds the
> OAuth 2.1 layer). Everything below describes the module once added.

Authentication lives in the **Auth module** (`modules/Auth/`), not in the app
kernel. It ships as a module so it travels between projects and can be
customized per client (see [modules.md](modules.md)). Removing the module
removes login — a project without it must register its own route named
`login` (the kernel navigates to it by name; see the route-name contract in
[modules.md](modules.md#the-kernel-contract)).

### Backend

**Driver: Laravel Sanctum.** SPA cookie/token auth for the browser, bearer
Personal Access Tokens (PATs) for mobile/Capacitor. The old JWT package is
gone.

Endpoints (all under `/api/v1`, defined in `modules/Auth/Routes/api.php`):

| Method + path                    | Purpose                                                  |
| -------------------------------- | -------------------------------------------------------- |
| `POST /auth`                     | Log in (email + password) → `{access_token, token_type}` (throttled 6/min) |
| `GET /auth`                      | Current user, or `{user: null}` for a guest (deliberately public) |
| `DELETE /auth`                   | Log out — revokes the current token                      |
| `POST /auth/impersonate`         | Issue a token acting as another user (`auth:sanctum`)    |
| `DELETE /auth/stop-impersonating`| Revoke the impersonation token (`auth:sanctum`)          |
| `POST /forgot-password`          | Send a reset email (200 even for unknown emails — no account enumeration) |
| `POST /forgot-password/reset`    | Reset the password with a token                          |

**Guard gotcha:** the default guard is `web`. Routes outside `auth:sanctum`
middleware (like `GET /auth`) must resolve the user via `$request->user('sanctum')`
— `$request->user()` uses the web guard and ignores bearer tokens.

**Password reset mail** is the module's `Modules\Auth\Mail\ForgotPasswordMail`
(view `auth::mail.forgot-password`). The kernel `User` model keeps the
framework's `CanResetPassword` trait untouched; the module's provider swaps the
notification's mailable via `ResetPassword::toMailUsing(...)` at boot. The
email links to the SPA `/set-password` page with the token + email.

You can still access the user the normal way: `auth()->user()` inside
`auth:sanctum` routes, or `$request->user('sanctum')` on public ones.

### Vue side

Handled by the [user store](../resources/ts/stores/user.ts) and the
[auth plugin](../resources/ts/plugins/auth.ts). Access the current user with
`this.$auth.user` (or `import {$auth} from '@/plugins/auth'` outside a
component). Methods:

- `this.$auth.login(form): Promise<AxiosResponse>` — logs in, stores the token.
- `this.$auth.logout(): Promise<void>` — logs out.
- `this.$auth.loadUser(force?)` — hydrates the user from the stored token.
- `this.$auth.impersonate(userId)` / `stopImpersonating()`.

The login + set-password pages live in the module
(`modules/Auth/resources/ts/pages/`) and register their own routes
(`modules/Auth/resources/ts/routes.ts`, Guest middleware, Empty layout).

## MCP server

The module exposes a Model Context Protocol server at **`POST /mcp`** (built on
`laravel/mcp`), so AI agents can call the app's tools as an authenticated user.
The server is `Modules\Auth\Mcp\AppServer`; add your tools to its `$tools`
array. The reference tool `who-am-i` (`Modules\Auth\Mcp\Tools\WhoAmITool`)
returns the acting user — copy its shape, delete it once you have real tools.

**Auth: `auth:sanctum` by default.** An MCP client authenticates with a
Personal Access Token as a bearer. Mint one:

```php
$user->createToken('claude-desktop')->plainTextToken;
```

Config (`config/auth.php` → `mcp`, env-driven): `AUTH_MCP_ENABLED` (default
true), `AUTH_MCP_PATH` (default `mcp`).

## OAuth 2.1 (optional layer)

For MCP clients that speak OAuth instead of a pasted token, the module ships an
optional Passport-backed OAuth 2.1 layer: authorization code + PKCE, refresh
tokens, and RFC 7591 Dynamic Client Registration, with the RFC 8414 / 9728
discovery documents MCP clients probe. **Off by default.**

### Turning it on

```sh
docker compose exec webserver php artisan auth:enable-oauth   # migrations + signing keys
# then set in .env:
AUTH_OAUTH_ENABLED=true
docker compose exec webserver php artisan config:clear
docker compose exec webserver php artisan route:clear
```

What flips on:

- Passport's `/oauth/*` routes + `.well-known/oauth-*` discovery + `POST /oauth/register` (DCR).
- The `/mcp` guard widens to `auth:sanctum,api` — it now accepts **both** a
  Sanctum PAT and an OAuth access token.
- The consent screen at `/oauth/authorize` (module view `auth::oauth.authorize`).
- Login/logout additionally open/close a **web session**, so a browser that
  logged into the SPA can approve `/oauth/authorize` without a second login. A
  guest who lands on the consent screen is bounced to `/login?to=…` and returns
  after logging in.

When the layer is off, `Passport::ignoreRoutes()` (called in the module
provider's `register()`) keeps the `/oauth/*` routes out of the app entirely.

### Keys on deploy

`auth:enable-oauth` writes `storage/oauth-{private,public}.key`. Don't ship the
files — copy them into `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` env vars
(see `.env.example`).

### The flow

1. Client fetches `.well-known/oauth-protected-resource` (advertised in the
   `WWW-Authenticate` header of any `401` from `/mcp`) → the auth server.
2. Client self-registers at `POST /oauth/register` → gets a public `client_id`.
3. Browser → `/oauth/authorize?...&code_challenge=...` → user approves →
   redirect to the client's callback with `?code=`.
4. Client exchanges the code + PKCE verifier at `POST /oauth/token` → access token.
5. Client calls `/mcp` with `Authorization: Bearer <access token>`.

The scope is `mcp:use`. The `User` model keeps Sanctum's `HasApiTokens`; the
OAuth flow issues its own tokens through Passport's tables and validates them
via the `api` guard, so the two token systems don't collide.
