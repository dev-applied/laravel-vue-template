---
type: context-index
---

# Context Index

Business + persona context. Code shows *what*. These docs show *why*, *for whom*, *under what constraints*.

## Folder Map

| Path | Purpose |
|------|---------|
| `product.md` | Business model, value prop, non-goals |
| `glossary.md` | Domain terms |
| `roadmap.md` | In-flight + planned work (read first for parallel-session sync) |
| `future-ideas.md` | Uncommitted ideas (parking lot) |
| `known-issues.md` | Accepted tech debt |
| `decisions/` | ADRs with supersession |
| `personas/` | User archetypes (narrative) |
| `features/<area>/` | Feature spec + per-persona matrix |
| `infrastructure/` | Envs + integrations |
| `workflows/` | Cross-feature journeys |
| `runbooks/` | Operational procedures |

## Persona × Feature Split

- **Feature file** → spec (matrix: what each persona sees/hides/defaults)
- **Persona file** → narrative (why they use it that way)

Read both when designing UI.

## Update Triggers

Codified in `~/.claude/skill-learnings/`:
1. Brainstorming → read context, propose updates in design output
2. Subagent-driven-dev → subagents report `Context divergences` in summaries
3. `/ship` → backstop for quick fixes
