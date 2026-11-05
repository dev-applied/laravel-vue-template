---
type: roadmap
---

# Roadmap

In-flight and planned work, grouped by concept. Read first for parallel-session sync. **Legend:** `- [x]` done (`: YYYY-MM-DD`) · `- [~]` in progress (branch or worktree) · `- [ ]` queued · `- [!]` blocked (`blocked-on:` reason).

<!-- context-init:roadmap-rules 3
FORMAT RULES (enforced by python3 .claude/context/check-roadmap.py; the applied-product
plugin also checks every edit to this file and reports what the edit broke).

Shape
- One `##` per concept (initiative, area, or standalone item). Never a section named for a
  status ("In Progress", "Done", "Backlog"). The one exception is `## Recently Done` at the bottom.
- Status lives only in the leaf marker. One status per line: never status in prose, never
  done and pending mixed in one bullet. A queued leaf may carry one `depends-on:` or
  `awaiting:` qualifier, never a completion claim.
- A leaf is exactly one line, under 200 characters. No wrapping, no nested sub-bullet, no
  paragraph. Findings and evidence go in features/, known-issues.md, decisions/ or a linked
  spec, and the leaf links there. Context prose for a concept sits above its first leaf.
- `[x]` ends with `: YYYY-MM-DD`. `[~]` names its branch or worktree (a parallel session greps
  it to avoid collisions). `[!]` carries `blocked-on:`.
- Heat order: every concept with a `[~]` leaf sorts above every concept without one.
- Recently Done (last 30 days): one line per entry, same 200-character cap, no nesting, each
  with a ship date. Trim entries older than 30 days. A ship that needs a narrative gets a
  handback doc, linked from the one line. Never duplicate a leaf's detail in both places.

Transitions (flip markers in place; leaves never move between concepts)
- Start: `[ ]` to `[~]` plus branch or worktree, and move the concept to the top. Work that
  is not here yet gets a new concept with its leaf `[~]`.
- Mid-flight: keep the branch current; add newly found sub-leaves to the concept.
- Pause: `[~]` back to `[ ]`, or `[!]` with `blocked-on:`.
- Ship: `[~]` to `[x]: YYYY-MM-DD` in place. Add a Recently Done line only for a substantial
  ship or a concept that is now fully done (then collapse that concept into the one line).

Examples
  Never:  P1 · Customer forms: F-15/F-16 done 2026-06-24. Remaining: F-23
  Always: - [x] F-15 launcher presence cue: 2026-06-24
          - [~] F-16 phone-optional-on-Video: feature/phone-optional
          - [ ] F-23 retain name/email/phone on cancel
-->

## [Active concept: has a `[~]` leaf, so it sorts near the top]

Optional one-line context (source doc, ticket, depends-on). For a cross-cutting feature, note integration points here: exposes [APIs/schemas/components] · schema [tables] · touches [shared code] · coordinating-with [other concept]. Context prose belongs above the first leaf, never between leaves.

- [~] Leaf in flight (refs): `feature/<name>`, `.worktrees/<name>`
- [ ] Leaf queued (refs)
- [x] Leaf shipped ([detail](features/<area>/<feature>.md)): YYYY-MM-DD
- [!] Leaf blocked (refs): blocked-on: [reason]

## [Queued concept: all `[ ]`, sits lower]

- [ ] Leaf one (refs). depends-on: [other concept].
- [ ] Leaf two (refs)

## [Standalone item]

- [ ] **[Name]**: [summary]. depends-on: [other item].

## Recently Done (last 30 days)

- [Name](features/...): shipped YYYY-MM-DD.
- [Concept name] ([handback](docs/...)): all leaves shipped YYYY-MM-DD.
