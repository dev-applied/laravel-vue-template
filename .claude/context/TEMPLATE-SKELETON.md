# This folder is an empty skeleton

Every project bootstrapped from this template starts with this `.claude/context/` folder: the unfilled files that `applied-product:context-init` scaffolds, plus its two validators. Running `/applied-product:context-init` in the new project fills them in.

- **Do not fill it in here.** Anything written into these files ships to every new client project.
- **Work on the template itself** is tracked in `docs/template-maintenance/` (roadmap, known issues, ideas).
- **This file and `docs/template-maintenance/` never reach a new project.** `.gitattributes` marks both `export-ignore`, and `bootstrap-repo` builds projects with `git archive`. The applied-product plugin also reads this file: it skips its "needs a re-audit" session-start line in the template repo.
- **To refresh the skeleton** after context-init's templates change, copy them over again (the list is in context-init's Phase 0).
