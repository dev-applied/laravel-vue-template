# Template maintenance

Notes about work on the template itself, not on projects built from it.

- `roadmap.md`: template work in flight and queued.
- `known-issues.md`: accepted gaps in the template and its CI.
- `future-ideas.md`: uncommitted ideas for the template and its modules.

These lived in `.claude/context/` until 2026-11-05. They moved here because every bootstrapped project inherited them as if they were its own history. This folder is `export-ignore` in `.gitattributes`, so `git archive` (and `bootstrap-repo`, which uses it) leaves it out of new projects. `.claude/context/` now holds the empty skeleton new projects start from.
