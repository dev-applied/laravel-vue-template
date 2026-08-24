---
type: roadmap
---

# Roadmap

In-flight + planned work, grouped **by concept** (one `##` per initiative / area / standalone item). Read first for parallel-session sync.

**Legend:** `- [x]` done (+ `— YYYY-MM-DD`) · `- [~]` in progress (+ `branch` / `worktree`) · `- [ ]` queued · `- [!]` blocked (+ reason). One status per line — never in prose, never mixed in one bullet. **Concepts with a `[~]` leaf sort to the top** — that's where a parallel session looks for what's hot, and the `branch` / `worktree` on each `[~]` leaf is how it avoids collisions.

## Deploy credentials — secrets over variables

GitHub **variables** are readable by every repo collaborator and are not masked in logs, so a project on variables exposes its deploy key and full production `.env`. `dev-applied/deploy-action` reads `secrets.X || vars.X` for every sensitive value (its PR #7, 2026-08-24) — but that path is **dead unless the caller workflow passes `secrets: inherit`**. Detail + migration procedure: the `client-deploy` runbook, credentials page.

- [~] `secrets: inherit` on the four deploy callers, + secrets-fallback and log-safe env handling in `deploy-lambda.yml` — feature/deploy-secrets-inherit
- [ ] Per-repo migration: create environment secrets → confirm one deploy → delete the variables → **rotate** (values that lived in variables are presumed disclosed) — depends-on: visilaunch/Vaultwarden GitHub-secrets sync
- [x] Rotation tiering rule + migration playbook in the `client-deploy` runbook — 2026-08-24
- [x] Truth-up: runbook named the decommissioned Infisical as canonical store; corrected to visilaunch/Vaultwarden — 2026-08-24

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

## Modules — evidence-ranked candidates (research 2026-08-24)

Counts are DISTINCT projects, machine-derived from 1,074 controllers and 2,828
migrations across 44 local Laravel repos. Full report:
`scratchpad/module-candidates.md`. These outrank the speculative queue below.

- [x] **RolesPermissions** (17 projects) — spatie-backed role CRUD + permission matrix UI, HasAccessControl trait supplying the `all_permissions` / `role` the kernel frontend already read, middleware aliases, grant-admin bootstrap, 16 tests — 2026-08-24
- [ ] **Support** (13) — contact form, option-gated up to full ticketing.
- [~] **Invitations** (13) — `master` (direct), modules repo `main` — — tokenized invite/accept. One appcando thread documents three client-visible bugs in a single hand-rolled invite flow.
- [ ] **Dashboard** (13) — KPI row, action queue, activity feed. Ship the shell with NO chart dependency and a named slot; the firm has no standard charting library.
- [~] **DataImport** (9) — `master` (direct), modules repo `main` — — the mirror of the shipped Exports module; washwerk's four-step CSV mapping wizard is liftable.
- [ ] **Announcements** (5) — best size-to-evidence ratio of the small ones.

## Modules — decisions the research forced

- [ ] **Tenancy sequencing** — SavedViews, Comments and Tasks are queued and all store per-user rows; AuditLog already shipped. If the firm wants tenant scoping, deciding late means retrofitting each. Not a blocker today (AuditLog reads through a project-defined gate, so a tenant-aware project scopes there), but it is Devin's call before the next per-user module.
- [ ] **Otp depends on an SMS channel** — Twilio appears in 10 projects and levelup couples OtpController to it directly. Otp should take a vendor option rather than hardcoding, or an SmsChannel module should land first.
- [ ] **SsoAuth should be an Auth option, not a module** — it is an auth strategy, and Auth already owns the option-variant pattern (sanctum | sanctum+oauth).

## Modules — candidate discovery

- [x] Mine runbooks, `~/.claude` docs and CSR history for missing module candidates — 14 candidates, 13 explicit rejections — 2026-08-24
- [x] Confirmed NOT to build: Impersonation (already in Auth — would have topped the list at 23 projects), ScheduledReports (0 evidence), ESignature (0), FeatureFlags (1) — 2026-08-24

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
