#!/usr/bin/env python3
"""Validate .claude/context/roadmap.md against the roadmap format rules.

Usage:
  check-roadmap.py [path-to-roadmap.md]                 (default: .claude/context/roadmap.md)
  check-roadmap.py --since PREVIOUS [path-to-roadmap.md]

Exit 0 = clean. Exit 1 = at least one FAIL. WARNs never change the exit code.

--since PREVIOUS reports only the FAILs that the current file has and PREVIOUS
did not: what an edit introduced. A roadmap that already failed before the edit
keeps its old failures out of the report, so a session flipping one leaf is not
told to reformat 700 lines it never touched. A failure counts as old when the
same rule fired on a line with the same text, so editing a failing line makes
its failure new: the session that touched the line fixes it.

WHY THIS EXISTS: the format rules used to live only as prose in the project's
CLAUDE.md. Prose rules that nothing checks drift, and roadmap edits happen at
ship time when the context window is fullest and prose recall is worst. Every
check below is mechanical: it fires on structure, not on judgment.
"""
from __future__ import annotations  # macOS system python3 is 3.9: keep `X | None` hints legal

import re
import sys
from collections import Counter
from datetime import date, datetime

LEAF_MAX = 200          # characters, whole line including the marker
RECENT_DAYS = 30        # Recently Done window
RECENT_HEADING = "recently done"

LEAF_RE = re.compile(r"^- \[([ x~!])\] ")
HEADING_RE = re.compile(r"^## +(.*?)\s*$")
DATE_RE = re.compile(r"(\d{4}-\d{2}-\d{2})")
TRAILING_DATE_OK = re.compile(r": \d{4}-\d{2}-\d{2}(\s*\*\(detail in Recently Done\)\*)?\s*$")
TRAILING_DATE_DASH = re.compile(r"[—–] ?\d{4}-\d{2}-\d{2}")
BRANCH_RE = re.compile(r"(?:feature|fix|spike|chore|hotfix|release|refactor)/[\w.\-/]+|\.worktrees/[\w.\-/]+")
DASH_RE = re.compile(r"[—–]")
# A dash inside a quoted span or inline code is reproduced text, never ours.
QUOTED_RE = re.compile(r"\"[^\"]*\"|'[^']*'|`[^`]*`|“[^”]*”")
# The shipped template is a scaffold: bracketed example headings and unfilled
# tokens are not defects. Skip them so a freshly scaffolded roadmap passes on
# day one. A validator that fails on the starter file teaches sessions to ignore it.
PLACEHOLDER_RE = re.compile(r"YYYY-MM-DD|<[A-Za-z][\w .\-/]*>|\.\.\.")
TEMPLATE_HEADING_RE = re.compile(r"^\[.*\]$")
STATUS_HEADINGS = {
    "in progress", "planned", "blocked", "done", "completed", "to do",
    "todo", "backlog", "queued", "shipped", "upcoming", "next up",
}
RULES_POINTER = ("Format rules: the context-init:roadmap-rules comment at the top of roadmap.md. "
                 "If the file has none yet, /applied-product:context-init adds it, and the "
                 "rules are also in templates/roadmap.md inside the context-init skill.")


class Finding:
    __slots__ = ("lineno", "msg", "detail", "text")

    def __init__(self, lineno: int, msg: str, detail: str, text: str):
        self.lineno, self.msg, self.detail, self.text = lineno, msg, detail, text

    def key(self) -> tuple[str, str]:
        return (self.msg, self.text.strip())


def check(text: str, today: date | None = None) -> tuple[list[Finding], list[Finding]]:
    """Return (fails, warns) for one roadmap's text."""
    lines = text.split("\n")
    today = today or date.today()
    fails: list[Finding] = []
    warns: list[Finding] = []

    def fail(lineno: int, msg: str, detail: str = "") -> None:
        fails.append(Finding(lineno, msg, detail, lines[lineno - 1] if lineno <= len(lines) else ""))

    def warn(lineno: int, msg: str, detail: str = "") -> None:
        warns.append(Finding(lineno, msg, detail, lines[lineno - 1] if lineno <= len(lines) else ""))

    heading = None              # current ## heading text, None = file preamble
    heading_line = 0
    in_template = False         # bracketed example heading from the shipped template
    seen_first_leaf = False     # have we passed the first leaf in this concept
    in_recent = False
    in_comment = False          # inside an HTML comment (the rules header lives in one)
    concept_order: list[tuple[str, bool, int]] = []   # (heading, has_tilde, lineno)
    has_tilde = False

    for i, raw in enumerate(lines, start=1):
        line = raw.rstrip()
        stripped = line.strip()

        if in_comment:
            if "-->" in stripped:
                in_comment = False
            continue
        if stripped.startswith("<!--"):
            in_comment = "-->" not in stripped
            continue

        m = HEADING_RE.match(line)
        if m:
            if heading is not None:
                concept_order.append((heading, has_tilde, heading_line))
            heading = m.group(1)
            heading_line = i
            seen_first_leaf = False
            has_tilde = False
            in_recent = heading.lower().startswith(RECENT_HEADING)
            in_template = bool(TEMPLATE_HEADING_RE.match(heading))
            bare = re.sub(r"\(.*?\)", "", heading).strip().lower()
            if not in_recent and bare in STATUS_HEADINGS:
                fail(i, f"status-named section '{heading}'. Group by concept; status lives in the leaf marker")
            continue

        if heading is None or not stripped:
            continue
        if in_template or PLACEHOLDER_RE.search(line):
            if LEAF_RE.match(line):
                seen_first_leaf = True
                if LEAF_RE.match(line).group(1) == "~":
                    has_tilde = True
            continue

        # --- Recently Done: one line per entry, no nesting, dated, in window ---
        if in_recent:
            if not stripped.startswith("- "):
                if seen_first_leaf:
                    fail(i, "Recently Done entry runs onto a second line. One line per entry; put the detail in the linked doc")
                continue
            seen_first_leaf = True
            if raw.startswith(" "):
                fail(i, "nested sub-bullet in Recently Done. One line per entry, no nesting")
                continue
            dates = DATE_RE.findall(line)
            if not dates:
                fail(i, "Recently Done entry carries no YYYY-MM-DD ship date")
            else:
                try:
                    shipped = datetime.strptime(max(dates), "%Y-%m-%d").date()
                    age = (today - shipped).days
                    if age > RECENT_DAYS:
                        fail(i, f"Recently Done entry is older than {RECENT_DAYS} days. Trim it", f"({age}d)")
                except ValueError:
                    fail(i, "unparseable date in Recently Done entry")
            if len(line) > LEAF_MAX:
                fail(i, f"Recently Done entry over {LEAF_MAX} chars. Link the detail, do not inline it",
                     f"({len(line)}c)")
            continue

        # --- active concepts ---
        leaf = LEAF_RE.match(line)
        if not leaf:
            if seen_first_leaf:
                fail(i, "line inside a concept is not a leaf. A leaf is exactly one line; context prose goes above the first leaf")
            continue

        seen_first_leaf = True
        marker = leaf.group(1)
        if marker == "~":
            has_tilde = True

        if len(line) > LEAF_MAX:
            fail(i, f"leaf over {LEAF_MAX} chars. Move the detail to features/, known-issues.md or a linked doc",
                 f"({len(line)}c)")

        if marker == "x":
            if TRAILING_DATE_DASH.search(line):
                fail(i, "done leaf dates with a dash. Use ': YYYY-MM-DD'")
            elif not TRAILING_DATE_OK.search(line):
                fail(i, "done leaf must end with ': YYYY-MM-DD' (an optional *(detail in Recently Done)* may follow)")
        else:
            if TRAILING_DATE_OK.search(line) or TRAILING_DATE_DASH.search(line):
                fail(i, f"'[{marker}]' leaf ends with a completion date. One status per line: never a done-claim on a leaf that is not [x]")

        if marker == "~" and not BRANCH_RE.search(line):
            fail(i, "in-progress leaf carries no branch or worktree ref. A parallel session greps this to avoid collisions")

        if marker == "!" and "blocked-on:" not in line:
            fail(i, "blocked leaf carries no 'blocked-on:' reason")

        for dm in DASH_RE.finditer(line):
            if not any(q.start() <= dm.start() < q.end() for q in QUOTED_RE.finditer(line)):
                warn(i, "dash outside quoted text. Two sentences, a colon, or parentheses. Leave dashes inside quotes alone")
                break

    if heading is not None:
        concept_order.append((heading, has_tilde, heading_line))

    # --- heat order: every concept with a [~] leaf sorts above every one without ---
    active = [c for c in concept_order if not c[0].lower().startswith(RECENT_HEADING)]
    last_hot = max((idx for idx, c in enumerate(active) if c[1]), default=-1)
    if last_hot >= 0:
        for idx, (name, hot, ln) in enumerate(active[:last_hot]):
            if not hot:
                fail(ln, f"'{name}' has no [~] leaf but sits above '{active[last_hot][0]}' which does. Heat-order the file")

    return fails, warns


def introduced(current: list[Finding], previous: list[Finding]) -> list[Finding]:
    """Findings in `current` that `previous` did not already have (multiset on rule + line text)."""
    old = Counter(f.key() for f in previous)
    new = []
    for f in current:
        if old[f.key()] > 0:
            old[f.key()] -= 1
        else:
            new.append(f)
    return new


def render(findings: list[Finding], label: str) -> str:
    """Group by rule, not one line per occurrence: one broken habit repeated 40
    times is one thing to fix, and a 300-line report gets skipped."""
    groups: dict[str, list[str]] = {}
    for f in findings:
        groups.setdefault(f.msg, []).append(f"{f.lineno}{f.detail}")
    out = [f"{label} ({len(findings)} across {len(groups)} rule(s)):"]
    for msg, lns in sorted(groups.items(), key=lambda kv: -len(kv[1])):
        shown = ", ".join(lns[:8])
        more = f" (+{len(lns) - 8} more)" if len(lns) > 8 else ""
        out.append(f"  [{len(lns)}x] {msg}")
        out.append(f"        lines: {shown}{more}")
    return "\n".join(out)


def read(path: str) -> str | None:
    try:
        return open(path, encoding="utf-8").read()
    except OSError as e:
        print(f"cannot read {path}: {e}", file=sys.stderr)
        return None


def main(argv: list[str]) -> int:
    since = None
    args = list(argv)
    if args[:1] == ["--since"]:
        if len(args) < 2:
            print("--since needs a path", file=sys.stderr)
            return 2
        since = args[1]
        args = args[2:]
    path = args[0] if args else ".claude/context/roadmap.md"

    text = read(path)
    if text is None:
        return 1
    fails, warns = check(text)

    if since is not None:
        prev_text = read(since)
        if prev_text is None:
            return 1
        prev_fails, prev_warns = check(prev_text)
        fails = introduced(fails, prev_fails)
        warns = introduced(warns, prev_warns)

    if warns:
        print(render(warns, "WARN"))
    if fails:
        if warns:
            print()
        print(path)
        print(render(fails, "FAIL"))
        print("\n" + RULES_POINTER)
        return 1
    print(f"roadmap OK: {path}" + (" (no new failures)" if since is not None else ""))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
