# Favorites

Star anything. A polymorphic pivot, a toggle endpoint, a trait for the models
being starred, a star button, and a "My favourites" page.

Built because the org-wide sweep found it hand-rolled in 7 of 39 Laravel repos —
the only genuinely new, recurring, extractable candidate that sweep turned up.

## Install

```sh
php artisan module:add Favorites
php artisan migrate
```

Then register what can be favourited, from `AppServiceProvider::boot()`:

```php
app(FavoritableRegistry::class)->register('article', Article::class, ability: 'view');
```

…and put the trait on those models:

```php
use Modules\Favorites\Concerns\Favoritable;

class Article extends Model
{
    use Favoritable;
}
```

## Endpoints

| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/favorites` | The caller's own favourites, newest first. `?type=` filters. |
| POST | `/api/v1/favorites/{type}/{id}` | Toggle. Returns `{favorited: bool}`. |
| DELETE | `/api/v1/favorites/{type}/{id}` | Explicit un-favourite. Idempotent. |

All require `auth:sanctum`. Every query is scoped to `$request->user()`, and no
endpoint takes a user id — there is deliberately no admin surface, because "what
has this person starred" is not an administrative question and building the
endpoint would make it one.

## Why the registry takes an `ability`

This is the part that is easy to get wrong, and the reason the default is
`'view'` rather than `null`.

The type arrives as a URL segment. Without an allow-list a caller could star any
Eloquent class in the app. That much is the same argument as
`CommentableRegistry` and `TaggableRegistry`.

The extra step here is that **a favourite is readable back**: `GET /favorites`
returns a *label* for every starred record. So without a per-record
authorization check, starring is a way to read the title of anything you can
name, and un-starring tells you whether it existed. Pass `ability: null` only
where the model genuinely has no per-record visibility.

## Toggle, not add/remove

A star is a two-state control, and the client should not have to know which
state it is in to change it — that read-then-write is exactly the race that
leaves a double-tap showing the opposite of the truth. The endpoint decides, and
the button renders what came back rather than flipping locally.

The unique index on `(user_id, favoritable_type, favoritable_id)` is what makes a
favourite a set membership rather than an event. Two tabs racing produce one row;
the loser's insert fails on the index and is treated as success, because the
favourite now exists, which is what that caller asked for.

## The trait

```php
$article->isFavoritedBy($user);                    // bool, and false for null
Article::query()->favoritedBy($user)->get();       // only this user's stars
Article::query()->withFavoritedBy($user)->get();   // eager-load, scoped
```

`withFavoritedBy` exists because `with('favorites')` would load **every** user's
rows — both a pointless amount of data and a disclosure, since how many other
people starred a record is not a list endpoint's business. `isFavoritedBy` uses
an already-loaded relation when there is one, so a list that eager-loaded does
not fire a query per row.

## Frontend

```vue
<AppFavoriteButton
  v-model="article.isFavorited"
  :label="article.title"
  :record-id="article.id"
  type="article"
/>
```

`modelValue` seeds the initial state; the component emits what the server
returned rather than a local flip.

## Deliberately not included

- **Counts.** "How many people starred this" is a different feature with
  different privacy properties, and the obvious implementation (a `withCount`
  on the same relation) is the disclosure `withFavoritedBy` exists to avoid.
- **Ordering / collections.** Folders of favourites is a real product, not a
  pivot table.
