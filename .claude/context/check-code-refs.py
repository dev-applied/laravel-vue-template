#!/usr/bin/env python3
"""Verify every `path.ext:line` code ref in .claude/context/ still points at what it names.

Usage: check-code-refs.py [context-dir] [repo-root]     (defaults: .claude/context, .)

Exit 0 = clean. Exit 1 = at least one FAIL. WARNs never change the exit code.

WHY THIS EXISTS: a doc's prose drifts from the code silently. Nothing in the repo
breaks, no test fails, and the doc keeps reading as authoritative. The mechanical
half of that drift IS checkable: a ref citing a line range that no longer contains
the symbol it names is wrong, provably, with no judgment call.

Deliberately conservative. It FAILS only when it can prove drift:
  - the file is gone
  - the line number is past the end of the file
  - the named symbol is in the file but NOT in the cited range (and it says where)
It WARNs when it cannot tell, so a clean exit means something.
"""
import re
import sys
from pathlib import Path

SLACK = 3           # tolerate small shifts around a cited range
SKIP_DIRS = {".git", "node_modules", "vendor", "dist", "build", ".claude", "storage"}
CODE_EXT = "php|vue|ts|js|jsx|tsx|scss|css|py|rb|go|java|json|yml|yaml"

# `path/or/File.ext:12` or `File.ext:12-30`, plus the bare `:12` continuation form
REF_RE = re.compile(r"`([A-Za-z0-9_./-]+\.(?:" + CODE_EXT + r")):(\d+)(?:-(\d+))?`")
BARE_RE = re.compile(r"`:(\d+)(?:-(\d+))?`")
TOKEN_RE = re.compile(r"`([^`\n]{2,60})`")
# A bare `:N` is only resolved from a full path ref earlier in the SAME list item
# (the `routes/api.php:115`, `:116`, `:117` shape). Inferring the owner from a class
# named in prose was tried and cut: `ProjectController::check()` (`:1022`) means
# ProjectController.php, `ConsolidatorController ... (`:38`) and throws `AppException``
# means ConsolidatorController, and every rule that got one shape right got another
# wrong. A checker that confidently names the wrong file is worse than one that
# declines, so anything it cannot resolve this way is reported as unverified.

fails: dict[str, list[str]] = {}
warns: dict[str, list[str]] = {}


def add(groups, msg, detail):
    groups.setdefault(msg, []).append(detail)


def render(groups, label):
    total = sum(len(v) for v in groups.values())
    print(f"{label} ({total} across {len(groups)} rule(s)):")
    for msg, items in sorted(groups.items(), key=lambda kv: -len(kv[1])):
        print(f"  [{len(items)}x] {msg}")
        for d in items[:10]:
            print(f"        {d}")
        if len(items) > 10:
            print(f"        (+{len(items) - 10} more)")


def index_repo(root: Path):
    """Map every basename and relative path to the files that carry it."""
    by_name: dict[str, list[Path]] = {}
    for p in root.rglob("*"):
        if not p.is_file():
            continue
        if any(part in SKIP_DIRS for part in p.parts):
            continue
        by_name.setdefault(p.name, []).append(p)
    return by_name


def resolve(ref: str, root: Path, by_name):
    direct = root / ref
    if direct.is_file():
        return direct, None
    hits = [p for p in by_name.get(Path(ref).name, []) if str(p).endswith(ref)]
    if not hits:
        hits = by_name.get(Path(ref).name, [])
    if len(hits) == 1:
        return hits[0], None
    if not hits:
        return None, "no such file"
    return None, f"ambiguous, {len(hits)} candidates"


def main() -> int:
    ctx = Path(sys.argv[1] if len(sys.argv) > 1 else ".claude/context")
    root = Path(sys.argv[2] if len(sys.argv) > 2 else ".")
    if not ctx.is_dir():
        print(f"no context dir at {ctx}", file=sys.stderr)
        return 1
    by_name = index_repo(root)
    checked = 0

    for doc in sorted(ctx.rglob("*.md")):
        lines = doc.read_text(encoding="utf-8", errors="replace").split("\n")
        last_ref = None
        for n, line in enumerate(lines, 1):
            where = f"{doc}:{n}"
            # A bare `:N` belongs to the nearest owner named BEFORE it, which may be a
            # file path or a class. Picking the last owner seen on the line attributed
            # `ConsolidatorController` refs to the `AppException` named after them.
            if not line.strip() or line.startswith(("#", "- ", "* ", "|")) or re.match(r"^\d+\. ", line):
                if not REF_RE.search(line):
                    last_ref = None

            anchors = []                       # (position, resolved-path-string)
            for am in REF_RE.finditer(line):
                anchors.append((am.start(), am.group(1)))
            anchors.sort()

            carried = last_ref            # owner inherited from an earlier line

            def owner_before(pos):
                prior = [a for a in anchors if a[0] < pos]
                return prior[-1][1] if prior else carried

            if anchors:                   # only after this line's refs are resolved
                last_ref = anchors[-1][1]

            for m in list(REF_RE.finditer(line)) + list(BARE_RE.finditer(line)):
                if m.re is REF_RE:
                    ref, start = m.group(1), int(m.group(2))
                    end = int(m.group(3)) if m.group(3) else start
                else:
                    ref = owner_before(m.start())
                    if not ref:
                        add(warns, "bare line ref with no resolvable file, verify by hand",
                            f"{where} -> `:{m.group(1)}`")
                        continue
                    start = int(m.group(1))
                    end = int(m.group(2)) if m.group(2) else start

                checked += 1
                path, why = resolve(ref, root, by_name)
                if path is None:
                    if why == "no such file":
                        add(fails, "code ref names a file that does not exist", f"{where} -> {ref}")
                    else:
                        add(warns, "code ref path is ambiguous, cannot verify", f"{where} -> {ref} ({why})")
                    continue

                body = path.read_text(encoding="utf-8", errors="replace").split("\n")
                if start > len(body):
                    add(fails, "code ref cites a line past the end of the file",
                        f"{where} -> {ref}:{start} (file has {len(body)} lines)")
                    continue

                # what does the doc claim is there? the backticked tokens after the ref
                rest = line[m.end():]
                tokens = [t for t in TOKEN_RE.findall(rest)
                          if not t.startswith(":") and "/" not in t.split(".")[0][:1]]
                if not tokens:
                    continue

                lo, hi = max(1, start - SLACK), min(len(body), end + SLACK)
                window = "\n".join(body[lo - 1:hi])
                whole = "\n".join(body)
                for tok in tokens[:2]:
                    core = tok.strip("`$@ ")
                    if not core or len(core) < 3:
                        continue
                    if core in window:
                        break
                    if core in whole:
                        hits = [i for i, l in enumerate(body, 1) if core in l]
                        actual = min(hits, key=lambda i: abs(i - start))
                        add(fails, "cited range no longer contains the symbol it names",
                            f"{where} -> {ref}:{start}{'-' + str(end) if end != start else ''} "
                            f"names `{core}`, which is now at :{actual}")
                        break
                    add(warns, "named symbol not found in the file it is cited from",
                        f"{where} -> {ref} names `{core}`")
                    break

    if warns:
        render(warns, "WARN")
    if fails:
        if warns:
            print()
        render(fails, "FAIL")
        print(f"\n{checked} code refs checked. Fix the drift, or correct the doc.")
        return 1
    print(f"code refs OK: {checked} checked in {ctx}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
