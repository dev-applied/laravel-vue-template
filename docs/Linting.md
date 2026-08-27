## Linting

Two linters run against this project. Both are wired into the `pre-commit` hook, so a
commit fails rather than lands unformatted.

| | Tool | Config | Command |
| --- | --- | --- | --- |
| Frontend | ESLint 9 (flat config) | `eslint.config.js` | `npm run lint` · `npm run fix` |
| Backend | Laravel Pint | `pint.json` | `./vendor/bin/pint` · `./vendor/bin/pint --test` |

Style references:

- **[Vue 3 style guide](https://vuejs.org/style-guide/)**
- **[Laravel preset](https://docs.styleci.io/presets#laravel)** — the base `pint.json` preset,
  before the rule overrides below.

---

## Pint: three rules that change behavior, not just formatting

Most of `pint.json` is whitespace and ordering. Three rules rewrite code in ways that can
change what it does. All three are safe on a project started from this template and applied
from commit one. All three are dangerous when the config is adopted into an existing
codebase and run across it in bulk.

### `mb_str_functions` — **off, deliberately. Do not turn it back on.**

The rule rewrites `strlen()` → `mb_strlen()`, `substr()` → `mb_substr()`, and so on for the
whole string family.

Those are not equivalent. `strlen()` counts **bytes**; `mb_strlen()` counts **characters**.
Any code holding binary — an encryption key, a hash, a signature, a raw file buffer — needs
the byte count, and the multibyte version silently returns a smaller number.

This bit a real project. A libsodium public key is 32 raw bytes; after the rewrite the length
check rejected every valid key, and the same pass rewrote 20 more call sites in the crypto
layer. It was caught by tests, but only because that path happened to be covered.

This template's own code was already rewritten while the rule was on — 13 files use `mb_*`.
Those all operate on module names and file paths, so they are correct as they stand and are
deliberately left alone. Turning the rule off just stops the next one being created.

If you genuinely want multibyte semantics somewhere, call `mb_strlen()` explicitly at that
site. Do not re-enable a rule that makes the choice for you across the whole codebase.

### `strict_comparison` — fine here, hazardous to retrofit

Rewrites `==` → `===` and `!=` → `!==`.

Correct in new code. The trap is retrofitting: **query parameters are always strings**, so
`request('page') === 1` is permanently false after the rewrite where `== 1` worked. The same
applies to any decimal column read through Eloquent's aggregates — `$rows->sum('hours')`
returns a string, so `=== 0` never matches again.

Neither of those throws. The suite stays green, Pint reports success, and the diff looks like
a tidy-up — the failure only shows up as a page rendering an empty state against a database
that is not empty. **If you apply this to an existing codebase, drive the affected screens in
a browser afterwards.** Tests alone will not catch it.

### `declare_strict_types` — fine here, hazardous to retrofit

Adds `declare(strict_types=1);` to every file.

Right for new code, and it surfaces genuine latent bugs. But it converts silent type coercions
that had been working for years into `TypeError`s at runtime. On one retrofit it turned
`strip_tags($int)` and `nl2br(null)` into production fatals — both had been coercing quietly
since they were written.

Same rule as above: applying this to an existing codebase means walking the app afterwards,
not just running the suite.

---

## Adopting this `pint.json` into an existing project

1. Turn `strict_comparison` and `declare_strict_types` **off** for the first pass, so the
   initial run is formatting only.
2. Commit that.
3. Turn them on one at a time, run `./vendor/bin/pint`, read the diff, and drive the affected
   screens in a browser before committing.

Skipping step 3 is how all three of the failures described above reached a running app.
