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
- [x] `module:add` prunes directories an option drop leaves empty — an empty `Mail/` reads as a broken install — 2026-08-24
- [x] `module:update` — the three-way merge from docs/modules.md, automated. `git merge-file` per file; `installed_options` replayed against both upstream trees so a pruned variant stays pruned; upstream deletions reported, never applied; conflicts exit non-zero. 10 tests against a real git repo, plus an end-to-end run with a genuine overlapping edit — 2026-08-24
- [x] `bin/lint` in the modules repo — pint AND eslint the whole repo from a template checkout in ONE direction, so a later rsync cannot silently revert the fixes. The round trip ate pint fixes once and eslint fixes once; covering only pint just moved which CI leg went red — 2026-08-24
- [x] `module:make` — scaffolds the full module anatomy from stubs that encode 21 modules' worth of review (newFactory, grouped search, through(), sometimes-on-update, ungated Vue route, flex-nowrap actions). Proven by generating a module and running it through the real pipeline: installs, 8 tests pass first try, pint/eslint clean, builds, vue-tsc 0, page loads in a browser — 2026-08-24
- [!] **Resource key casing is split-brain** — kernel snake_case (`due_date`, `owner_id`, `full_name`) vs camelCase in all 13 modules (`dueAt`, `isSelf`). A project installing modules gets a mixed API. blocked-on: **Devin's call, not sweepable unilaterally.** Both directions cost real work and neither is obviously right: making the kernel camelCase breaks its alignment with the Wayfinder/`models.d.ts` types, which are generated from the PHP models and are therefore snake_case, and forces AuthUserResource to hand-list fields rather than pass the model; making the 13 modules snake_case touches ~340 tests and every module frontend. Note either way that REQUEST payloads must stay snake_case regardless — form field names have to match the FormRequest rule keys that `getErrors('due_date')` looks up — so the outcome is an asymmetry (snake in, camel out) or a full snake_case sweep.
- [x] `module:add` now clears `node_modules/.vite`. A plain dev-server restart keeps the Vuetify plugin's cached virtual modules, so after a module changed the globs every component 404'd on its `.sass` and the app rendered blank with nothing naming the cause — three debugging cycles before the pattern showed — 2026-08-24
- [ ] `@vue/test-utils` is not a dependency, so component tests mount by hand via `createApp`. Add it, or document the hand-rolled pattern in `resources/ts/CLAUDE.md` — right now the next person will assume it exists.

## Modules — generic verticals (built fresh, washwerk as design reference only)

Decision 2026-08-24: read washwerk's 34 production modules for shape, write fresh generic code — no client code copied. Priority order; `docs/modules.md` explicitly names OTP / subscriptions / exports / booking as target verticals.

- [x] **Notifications** — feed, unread count, mark-read/all, dismiss, ExampleNotification, bell + wired container + page + polling composable, 10 tests — 2026-08-24
- [x] **Exports** — registry allow-list, queued streaming job, CSV native + XLSX option, export button + history page, 14 tests — 2026-08-24
- [x] **Otp** — passwordless sign-in + step-up, OtpChannel seam (email shipped, SMS bound by the project), hashed/single-use/attempt-capped codes, dual rate limiting, enumeration-safe responses, env-gated QA bypass, 30 tests — 2026-08-24
- [x] **SavedViews** — named filter sets per screen, opaque payload, default view, read-only sharing, 422 on duplicate names, SavedViewScope tenancy seam, 23 tests — 2026-08-24
- [x] **Comments** — HasComments trait, CommentableRegistry allow-list, internal notes, explicit-token @mentions firing UserMentioned (event, not a notification), `threading` option, 29 tests — 2026-08-24
- [x] **AuditLog** — Auditable trait, field-level diffs, secret redaction, gated read API, record timeline, retention prune, 14 tests — 2026-08-24
- [x] **Settings** — registry-declared typed settings, self-generating UI, secret masking, one-entry cache, 22 tests — 2026-08-24
- [x] **Tags** — HasTags trait, TaggableRegistry, slug-as-identity normalisation, AND-by-default scopes, merge endpoint, tags:dedupe for legacy data, 29 tests — 2026-08-24
- [x] **Billing** — RevenueCat-webhook-authoritative entitlements (NOT direct Stripe, per the hybrid-billing runbook), ordering-safe idempotency ledger, transfer branch, tier middleware, env-gated QA switcher + billing:assert-safe pre-deploy guard, 65 tests — 2026-08-24
- [x] **Booking** — weekly availability in the resource's timezone, blackouts, capacity, notice/advance windows, lockForUpdate double-booking prevention, `approval` option, 36 tests — 2026-08-24
- [x] **FormBuilder** — schema-snapshotted submissions, server-derived validation, save-time schema validation, kernel field components inside AppServerValidationForm, 28 tests — 2026-08-24
- [x] **Tasks** — HasTasks trait, StatusMachine transition table surfaced as nextStatuses, derived completed_at, event seams, `board` option (list | +kanban), 30 tests — 2026-08-24

## Modules — evidence-ranked candidates (research 2026-08-24)

Counts are DISTINCT projects, machine-derived from 1,074 controllers and 2,828
migrations across 44 local Laravel repos. Full report:
`scratchpad/module-candidates.md`. These outrank the speculative queue below.

- [x] **RolesPermissions** (17 projects) — spatie-backed role CRUD + permission matrix UI, HasAccessControl trait supplying the `all_permissions` / `role` the kernel frontend already read, middleware aliases, grant-admin bootstrap, 16 tests — 2026-08-24
- [x] **Support** (13) — contact form option-gated up to full ticketing (threaded replies, assignment, status), 18 tests — 2026-08-24
- [x] **Invitations** (13) — tokenized invite/accept, hashed-at-rest tokens, expiry + single-use, 16 tests. One appcando thread documented three client-visible bugs in a single hand-rolled invite flow — 2026-08-24
- [x] **Dashboard** (13) — registry shell, batched endpoint, ability filtering, per-user cache, error isolation, named chart slot, 11 tests — 2026-08-24
- [x] **Announcements** (5) — banner/modal, scheduling windows, per-user dismissal, acknowledgement, fail-closed AudienceResolver, `delivery` option (in-app | +email), 27 tests — 2026-08-24
- [x] **DataImport** (9) — registry + queued job mirroring Exports, four-step CSV mapping wizard, 16 tests — 2026-08-24

## Modules — decisions the research forced

- [ ] **Tenancy sequencing** — SavedViews shipped with a `SavedViewScope` seam (bind once, whole module covered) — that is the pattern the remaining per-user modules should copy, and it makes this decision cheap to defer rather than free to ignore. Comments and Tasks are queued and both store per-user rows; AuditLog already shipped. If the firm wants tenant scoping, deciding late means retrofitting each. Not a blocker today (AuditLog reads through a project-defined gate, so a tenant-aware project scopes there), but it is Devin's call before the next per-user module.
- [x] **Otp depends on an SMS channel** — RESOLVED by the seam pattern three modules have now used (AudienceResolver, SavedViewScope, CommentableRegistry): Otp declares an `OtpChannel` contract and ships email; a project binds Twilio or anything else. No SmsChannel module has to land first, and nothing about Twilio is baked in — 2026-08-24
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

- [x] **41 open npm Dependabot alerts cleared** (2 critical, 22 high) — `npm audit fix`, lockfile only, 0 vulnerabilities after; build + vitest + eslint green. Root cause was not a broken config: npm sat at exactly `open-pull-requests-limit: 10`, and at the limit Dependabot stops opening new PRs INCLUDING security ones, so every advisory queued behind five routine bumps nobody merged — 2026-08-24

- [x] Template CI re-enabled on `pull_request` (no branch filter, so it runs whether the repo calls its trunk `master` or `main`) and feature pushes. Dispatched first rather than uncommenting and hoping: it caught ExampleTest demanding a built Vite manifest in a job that never runs npm. All three jobs green — 2026-08-24
- [x] `vue-tsc --noEmit` clean — 42 errors (not the recorded 73; the Vuetify v4 work had already cut it) down to 0. Most were wrong DECLARATIONS hiding real bugs: `$confirm` declared with its first two parameters reversed, AppAutoComplete passing its axios instance under the wrong option name so creates went out unauthenticated, `extractId` using a function as an object key, `reload(resetPage)` ignoring its argument, AppListTable skipping a page on a cancelled request, and ItemFormPage reading past the `data` envelope so the canonical CRUD example could never edit a record — 2026-08-24
- [x] Vitest drops the dev server to a stale bundle — RETESTED 2026-08-24, does not reproduce. `npm run test:ci` run the sanctioned way (`docker compose exec frontend`) leaves `public/hot` untouched (same mtime and contents before/after, app still 200s), across three runs. `git stash list` is also empty, so the recorded fix location no longer exists. Reopen with a fresh repro if it returns — the old blocker record was stale and was keeping a non-issue on the board — 2026-08-24

## Recently Done (last 30 days)

- User payload allow-listed 2026-08-24 — AuthUserResource returned the whole model, so any module adding a `users` column shipped it to every client (the Users module already had). Five explicit fields, and the roles/permissions checks no longer lazy-load.
- Template CI is live again 2026-08-24 — nothing had gated a PR; now pint+pest, eslint+vue-tsc+vitest, and a Capacitor build smoke all run on every pull request.
- `module:make` 2026-08-24 — new modules start from the canonical shape, green on every check before a line is edited.
- Frontend type safety — 42 vue-tsc errors to 0 on 2026-08-24, and the six real bugs the broken declarations were hiding (unauthenticated autocomplete creates, an edit form that silently wiped records, a reversed `$confirm` signature).
- Modules — extraction from the template — all leaves shipped 2026-08-24 (Files and Users lifted out of the kernel; the kernel keeps only a read-only `users` typeahead).
- Kernel slot-forwarding bug fixed 2026-08-24 — six components blanked the ENTIRE page when the wrapped Vuetify component invoked a zero-argument slot (`no-data`, `loading`, `top`, `bottom`). Any list screen using `#no-data` was dead. Pinned by a vitest spec that reproduces the throw.
- Modules CI harness — went from every-run-red to fully green 2026-08-24 (4 real bugs: public-repo token, phantom COMPOSER_AUTH, unbuilt frontend, Example collision).
