## Summary

<!-- 1-3 bullets. Focus on the *why* — the diff already shows the *what*. -->

-
-

## Test plan

<!-- Specific steps a reviewer or QA could replay. Replace these examples. -->

- [ ]
- [ ]

## Screenshots / video

<!-- Drop attachments here for any UI change. Before / after if it's a refactor. -->

## Quality checklist

- [ ] `composer ci` passes locally (pint + pest --parallel)
- [ ] `npm run lint` and `npm run type-check` pass locally
- [ ] If routes/controllers changed: ran `composer typescript` to regen Wayfinder TS types
- [ ] Tests added/updated for the new behavior (Feature for new endpoints, Unit/Vitest for composables)
- [ ] Permission-gated routes are gated UI-side too (button doesn't appear if 403 would fire)
- [ ] Empty state, loading state, and error state are all visible in the UI
- [ ] No hex / rgba literals in SCSS or templates (Vuetify theme tokens only)
- [ ] No `migrate:fresh` in any committed script (and no destructive ops on the prod path)
- [ ] If Capacitor / mobile is in scope: tested on at least one native target

## Out of scope

<!-- Anything intentionally NOT in this PR that a reviewer might wonder about. -->

-
