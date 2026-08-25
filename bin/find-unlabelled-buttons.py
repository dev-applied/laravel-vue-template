#!/usr/bin/env python3
"""Report every icon-only <v-btn> that has no accessible name.

Split out of bin/lint-kernel so the matching rules live somewhere readable.
Both refinements below came from being wrong in production:

  prepend-icon / append-icon decorate a button that still has TEXT. Matching a
  bare "icon" substring reported AppConfirmActionButton and a menu activator,
  neither of which is icon-only.

  A mustache is not a label. Excluding any button with inner text dropped the
  three password-reveal toggles, whose content is
  `{{ !showPassword ? 'visibility' : 'visibility_off' }}` — an interpolated
  ligature NAME, on the public login page.
"""
import glob
import re
import sys

root = sys.argv[1] if len(sys.argv) > 1 else "resources/ts"
bad = []

for path in sorted(glob.glob(f"{root}/**/*.vue", recursive=True)):
    source = open(path).read()

    for match in re.finditer(r"<v-btn\b([^>]*?)(/>|>)", source, re.S):
        attrs = match.group(1)

        if not re.search(r"(^|\s):?icon(=|\s|$)", re.sub(r"(prepend|append)-icon", "", attrs)):
            continue
        if "aria-label" in attrs:
            continue

        if match.group(2) == ">":
            inner = source[match.end():].split("</v-btn>")[0]
            text = re.sub(r"\{\{.*?\}\}", "", re.sub(r"<[^>]*>", "", inner)).strip()

            if text:
                continue

        bad.append(f"{path}:{source[:match.start()].count(chr(10)) + 1}")

for entry in bad:
    print(f"  {entry}")

sys.exit(1 if bad else 0)
