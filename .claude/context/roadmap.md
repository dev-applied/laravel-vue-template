---
type: roadmap
---

# Roadmap

In-flight + planned work, grouped **by concept** (one `##` per initiative / area / standalone item). Read first for parallel-session sync.

**Legend:** `- [x]` done (+ `— YYYY-MM-DD`) · `- [~]` in progress (+ `branch` / `worktree`) · `- [ ]` queued · `- [!]` blocked (+ reason). One status per line — never in prose, never mixed in one bullet. **Concepts with a `[~]` leaf sort to the top** — that's where a parallel session looks for what's hot, and the `branch` / `worktree` on each `[~]` leaf is how it avoids collisions.

## Modules — CI + distribution harness

The `dev-applied/laravel-vue-modules` repo and the `module:add` / `module:configure` / `module:outdated` flow. Contract: `docs/modules.md`. Green CI = safe to `module:add`.

- [x] Drop `TEMPLATE_REPO_TOKEN` — template repo is public; empty token failed checkout — 2026-08-24
- [x] Drop `COMPOSER_AUTH` — nothing in composer.lock resolves from the private registry — 2026-08-24
- [x] Build the frontend in CI so module Vue halves are actually compiled — 2026-08-24
- [x] Clear template-bundled module copies before `module:add` (Example collision) — 2026-08-24
- [x] Module plugin glob — modules can now ship `resources/ts/plugin.ts` to register globals / Vue plugins — 2026-08-24
- [x] `npm_requires` — modules can declare npm deps; recorded in package.json when node is off PATH — 2026-08-24
- [ ] `module:update` — the automated three-way merge described in docs/modules.md but not built
- [ ] Module scaffolding command (`module:make`) so new modules start from the Example shape

## Modules — extraction from the template

Pulling shipped verticals out of the kernel and into the modules repo. Decision 2026-08-24: **true extraction** — deleted from the template, projects run `module:add`. Kernel contract in `docs/modules.md` shrinks accordingly. Items stays in the template as the worked example; Example stays as the reference module.

- [x] **Files** — whole vertical moved to `modules/Files`, four latent bugs fixed, 17 tests added, `storage` option (local | s3-presigned) — 2026-08-24
- [ ] **Users** — UserController, user-management CRUD + pages. User *model* stays kernel (Auth depends on it).

## Modules — generic verticals (built fresh, washwerk as design reference only)

Decision 2026-08-24: read washwerk's 34 production modules for shape, write fresh generic code — no client code copied. Priority order; `docs/modules.md` explicitly names OTP / subscriptions / exports / booking as target verticals.

- [x] **Notifications** — feed, unread count, mark-read/all, dismiss, ExampleNotification, bell + wired container + page + polling composable, 10 tests — 2026-08-24
- [x] **Exports** — registry allow-list, queued streaming job, CSV native + XLSX option, export button + history page, 14 tests — 2026-08-24
- [ ] **Otp** — one-time-code auth (email + SMS), vendor-swappable via module options. Ships its env-gated QA bypass per the qa-affordances rule.
- [ ] **SavedViews** — per-user persisted table filters / column prefs. Pairs with useFilters + AppPaginationTable.
- [ ] **Comments** — polymorphic comments/notes on any model, with mentions.
- [x] **AuditLog** — Auditable trait, field-level diffs, secret redaction, gated read API, record timeline, retention prune, 14 tests — 2026-08-24
- [ ] **Settings** — typed key/value app settings with a management UI.
- [ ] **Tags** — polymorphic tagging + filter integration.
- [ ] **Billing** — Stripe subscriptions, plans, entitlement seams. Carries its entitlement-state QA affordances.
- [ ] **Booking** — resource + availability scheduling.
- [ ] **FormBuilder** — dynamic form definitions rendered through the field component library.
- [ ] **Tasks** — assignable tasks, due dates, status transitions.

## Modules — candidate discovery

- [ ] Mine runbooks, `~/.claude` docs, and CSR conversation history for module candidates the roadmap is missing. Feeds this file rather than shipping code.

## Project context docs

`.claude/context/` scaffolded 2026-08-24 during the module run. Only the phases the run needed were completed.

- [x] Phase 0 scaffold + `## Project Context` block injected into CLAUDE.md — 2026-08-24
- [x] Phase 9 roadmap seeded as the run's work registry — 2026-08-24
- [ ] Phases 2-8, 10 — product, glossary, personas, features, workflows, infrastructure/SSH access, integrations, runbooks, ADRs. Several need Devin present (SSH keys, account owners, business model).

## Template health

- [ ] Re-enable the template's own `.github/workflows/ci.yml` — currently `workflow_dispatch` only, everything else commented out
- [ ] `vue-tsc --noEmit` type errors on master (73 recorded 2026-05-14) — never gated by CI
- [!] Vitest drops the dev server to a stale bundle — the Laravel Vite plugin's cleanup hook deletes `public/hot`. Fix exists uncommitted in `git stash@{0}`. blocked-on: needs its own branch, not the main checkout.

## Recently Done (last 30 days)

- Modules CI harness — went from every-run-red to fully green 2026-08-24 (4 real bugs: public-repo token, phantom COMPOSER_AUTH, unbuilt frontend, Example collision).
