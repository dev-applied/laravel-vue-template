# Settings

Typed application settings with a management UI that generates itself from the
declarations.

Every client project accumulates values that live in `.env` but shouldn't: a
support address, a per-page default, a feature toggle, a maintenance banner.
Changing one means a deploy, so nobody changes them.

## What it gives you

| Piece | What it does |
|---|---|
| `SettingRegistry` | Declares what settings exist, their type, group and validation. |
| `setting('key')` | Cached read, safe in a view or a loop. |
| `/settings` | Management payload — declarations plus values, grouped. |
| `/settings/public` | The public subset, readable signed out. |
| `SettingsPage.vue` | The whole screen, generated from the declarations. |

## Install

```sh
php artisan module:add Settings
php artisan migrate
```

No options, no composer dependencies.

## Declare your settings

```php
use Modules\Settings\Support\SettingRegistry;

public function boot(SettingRegistry $settings): void
{
    $settings->add('site.name', 'Site name', [
        'default' => config('app.name'),
        'public'  => true,          // readable signed out
    ]);

    $settings->add('support.email', 'Support address', [
        'group' => 'Contact',
        'rules' => ['email'],       // stacks on top of the type's own rules
    ]);

    $settings->add('limits.per_page', 'Rows per page', [
        'type'    => 'integer',
        'default' => 25,
        'rules'   => ['min:10', 'max:200'],
    ]);

    $settings->add('theme.mode', 'Theme', [
        'type'    => 'select',
        'choices' => ['light' => 'Light', 'dark' => 'Dark', 'system' => 'Follow system'],
        'default' => 'system',
    ]);

    $settings->add('stripe.key', 'Stripe secret key', [
        'group'  => 'Billing',
        'secret' => true,           // never returned to the client
    ]);
}
```

Types: `string`, `text`, `boolean`, `integer`, `float`, `select`, `json`.

Then define who may manage them — undefined denies, which is the right way
round for a screen that changes app behaviour:

```php
Gate::define('manage-settings', fn ($user) => $user->isAdmin());
```

## Read them

```php
setting('support.email')            // cached
setting('features.beta', false)     // with a fallback
```

The management screen needs no work: `SettingsPage.vue` builds its tabs, its
fields and its validation from the declarations. Adding a setting server-side
is the entire change.

## Design decisions worth knowing

**Nothing undeclared is writable.** A free-form key/value store accumulates
typos silently — `site_name` and `siteName` both present, one read by nothing —
and gives the UI no way to know whether a value is a boolean, a URL or a
credential. An unknown key is a 422, not a silent ignore, because ignoring it
makes a typo look saved.

**The whole table is one cache entry.** Settings get read on nearly every
request; a query per key is the classic mistake. Any write flushes it, and a
multi-setting save flushes once at the end rather than per key — otherwise
saving a screenful thrashes the cache and every concurrent request rebuilds it.

**A secret's value never leaves the server.** The API sends a mask when one is
set and `null` when it isn't, so the UI can say "not configured" rather than
implying otherwise. Sending the mask back means "unchanged" — without that,
opening the settings screen and pressing Save would overwrite every credential
with asterisks.

**A setting marked both public and secret is withheld.** Declaring both is a
mistake and the safe reading of it is "secret".

**Values are validated raw, then cast.** Casting first turns `"not a number"`
into `0` and the integer rule can never fail.

**Dotted keys need escaped rule paths.** `settings.limits.per_page` as a
validation key makes Laravel look for a *nested* field that does not exist, so
the rule matches nothing and everything validates — a silent pass on every
value. The rule key escapes the dot: `settings.limits\.per_page`. Worth knowing
if you extend this module's validation.

**An unset setting falls back to its declared default.** A fresh install should
behave like a configured one, not a broken one.

**Groups and keys are sorted.** The screen does not reshuffle between deploys.

## Frontend

- `SettingsPage.vue` — the management screen. Route `ROUTES.SETTINGS` →
  `/settings`.
- `AppSettingField` — renders one declared setting as the right control.
- `useSettings()` — `groups`, `loading`, `saving`, `loaded`, `fetch()`,
  `save(values)`. After a save it takes the server's echo rather than the local
  form, which is what re-masks a secret that was just replaced.
- `SECRET_MASK` — the sentinel meaning "unchanged".
