#!/usr/bin/env python3
"""Report class names that look like Vuetify utilities but exist nowhere.

The general form of a failure this codebase keeps shipping. A class name is
never validated by anything: eslint sees a string, vue-tsc sees a string, the
suite sees a string, and the browser silently drops a selector it has no rule
for. The element just keeps its default and the page looks *nearly* right.

Three instances shipped before this existed, each found only by loading a page:

    text-h4            81 usages - Vuetify 4 renamed the whole typography scale
                       to MD3, so every heading fell back to a browser default
    theme--light       a Vuetify 2 class; v3/v4 put v-theme--<name> on the root,
                       so the 404 page rendered white on white for two majors
    mediumgray--text   a colour never defined, in a v2 suffix v3 replaced

Rather than name each known-bad pattern, this compares every static class token
against the classes Vuetify actually ships plus the ones this project defines,
and reports the leftovers that are shaped like a utility. It therefore catches
the next one too, including a plain typo (`justify-space-betwen`).

Usage: find-unknown-utility-classes.py <src-dir> <vuetify-styles-dir> [theme-dir]

theme-dir defaults to resources/ts and is separate from src-dir on purpose:
when this scans modules/, the theme it must validate colours against still
lives in the kernel. Without that split every module's `text-primary` was
reported as unknown.
"""
import glob
import os
import re
import sys

src = sys.argv[1] if len(sys.argv) > 1 else "resources/ts"
styles = sys.argv[2] if len(sys.argv) > 2 else "node_modules/vuetify/lib/styles"
theme = sys.argv[3] if len(sys.argv) > 3 else "resources/ts"

CLASS_IN_CSS = re.compile(r"\.(-?[_a-zA-Z][\w-]*)")
CLASS_ATTR = re.compile(r'\bclass="([^"{}]*)"')
STYLE_BLOCK = re.compile(r"<style[^>]*>(.*?)</style>", re.S)

# Only tokens in these families are reported. A component's own BEM class
# (`app-list-table__search`) or a bare state class (`satisfied`) is defined in
# some stylesheet or applied by JS, and guessing about those is how a guard
# becomes noise nobody reads. A utility prefix is a claim about Vuetify's API,
# so a miss there is unambiguous.
UTILITY_PREFIXES = (
    "text-", "bg-", "d-", "flex-", "align-", "justify-", "ga-", "gc-", "gr-",
    "rounded", "elevation-", "overflow-", "position-", "opacity-", "order-",
    "float-", "border-", "w-", "h-", "v-theme--", "font-weight-", "text--",
)
SPACING = re.compile(r"^[mp][atbeslrxy]?-(auto|n?\d+)$")


def css_classes(directory):
    found = set()
    for name in ("utilities.css", "main.css", "colors.css"):
        path = os.path.join(directory, name)
        if os.path.exists(path):
            found |= set(CLASS_IN_CSS.findall(open(path, errors="ignore").read()))
    return found


def project_classes(root):
    """Classes this project defines itself — SCSS files and every <style> block.

    Read from ALL files, not just the file being checked: a class is often
    declared in one component's unscoped block and used in another.
    """
    found = set()
    for path in glob.glob(f"{root}/**/*", recursive=True):
        if not path.endswith((".scss", ".css", ".sass", ".vue")):
            continue
        text = open(path, errors="ignore").read()
        blocks = STYLE_BLOCK.findall(text) if path.endswith(".vue") else [text]
        for block in blocks:
            found |= set(CLASS_IN_CSS.findall(block))
    return found


def theme_colour_classes(root):
    """`text-error` / `bg-primary` are real but are in no shipped stylesheet.

    Vuetify generates a utility per theme colour at runtime from the theme
    config, so scanning CSS alone reports every one of them as unknown. Read the
    colour names out of the theme definition and seed the known set with them —
    which also means a colour that is NOT in the theme still gets reported.
    """
    names = set()
    for path in glob.glob(f"{root}/**/*.ts", recursive=True):
        text = open(path, errors="ignore").read()
        if "ThemeDefinition" not in text and "createVuetify" not in text:
            continue
        for block in re.findall(r"colors:\s*\{(.*?)\n\s*\}", text, re.S):
            names |= set(re.findall(r"['\"]?([a-z][\w-]*)['\"]?\s*:", block))
    out = set()
    for name in names | {"surface", "background", "on-surface", "surface-variant",
                         "surface-light", "surface-bright", "medium-emphasis",
                         "high-emphasis", "disabled"}:
        out |= {f"text-{name}", f"bg-{name}", f"border-{name}"}
    return out


def reportable(token, known):
    if token in known or SPACING.match(token):
        return False
    return any(token.startswith(p) for p in UTILITY_PREFIXES)


if not os.path.isdir(styles):
    print(f"  ! {styles} not found — cannot check class names. Run npm ci first.")
    sys.exit(2)

known = css_classes(styles) | project_classes(src) | theme_colour_classes(theme)
if len(known) < 500:
    print(f"  ! only {len(known)} known classes parsed — that is too few to trust.")
    sys.exit(2)

bad = []
for path in sorted(glob.glob(f"{src}/**/*.vue", recursive=True)):
    text = open(path, errors="ignore").read()
    template = STYLE_BLOCK.sub("", text)
    for match in CLASS_ATTR.finditer(template):
        line = template[:match.start()].count("\n") + 1
        for token in match.group(1).split():
            if reportable(token, known):
                bad.append(f"{path}:{line}  {token}")

for entry in bad:
    print(f"  {entry}")

sys.exit(1 if bad else 0)
