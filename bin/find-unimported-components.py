#!/usr/bin/env python3
"""
A <script setup> component that uses another component in its template resolves
it ONLY if the file imports it. Miss the import and Vue logs

    [Vue warn]: Failed to resolve component: app-date-input

and renders NOTHING in its place. It is a warning, not an error: the build
passes, the tests pass, the page loads, and the component is simply absent on
screen. AppDateRangePicker shipped that way — preset chips visible, both date
fields missing.

Usage: find-unimported-components.py <src-dir> [<src-dir> ...]
Exit 0 clean, 1 findings.
"""
import re
import sys
from pathlib import Path

# Resolved by the framework, not by an import.
BUILTIN = {
    "component", "template", "slot", "transition", "transition-group",
    "keep-alive", "teleport", "suspense", "router-view", "router-link",
}


def kebab(name: str) -> str:
    return re.sub(r"(?<!^)(?=[A-Z])", "-", name).lower()


def check(path: Path) -> list[str]:
    text = path.read_text(encoding="utf-8", errors="replace")

    # Anchored to the start of a line: an SFC block tag is always top-level.
    # Without the anchor this matches the words "<script setup>" written
    # inside a COMMENT — NotificationsPage.vue has a docblock explaining why
    # it does not use script setup, and that alone made it a finding.
    m = re.search(r"^<script[^>]*\bsetup\b[^>]*>(.*?)^</script>", text, re.S | re.M)
    if not m:
        return []                      # Options API registers via `components:`
    script = m.group(1)

    tm = re.search(r"^<template>(.*)^</template>", text, re.S | re.M)
    if not tm:
        return []
    template = tm.group(1)

    imported = set()
    # Default import, with or without a trailing named list:
    #   import Foo from './Foo.vue'
    #   import Foo, { FooProps } from './Foo.vue'   <- the comma form
    for im in re.finditer(r'import\s+(\w+)\s*(?:,\s*\{[^}]*\})?\s*from', script):
        imported.add(kebab(im.group(1)))
    # `import { A, B } from "./x.vue"` and re-exported locals
    for im in re.finditer(r'import\s*\{([^}]+)\}\s*from\s*["\'][^"\']+["\']', script):
        for part in im.group(1).split(","):
            imported.add(kebab(part.split(" as ")[-1].strip()))
    # defineAsyncComponent / local const holding a component
    for im in re.finditer(r"(?:const|let)\s+(\w+)\s*=", script):
        imported.add(kebab(im.group(1)))

    findings = []
    seen = set()
    for tag in re.findall(r"<([A-Za-z][\w.-]*)", template):
        k = kebab(tag)
        if k in seen:
            continue
        seen.add(k)
        if "-" not in k:               # plain HTML element
            continue
        if k in BUILTIN or k in imported:
            continue
        if k.startswith("v-"):         # Vuetify, globally registered
            continue
        findings.append(f"{path}: <{tag}> is used but never imported")

    return findings


def main(argv: list[str]) -> int:
    roots = [Path(a) for a in argv[1:]] or [Path("resources/ts")]
    findings = []
    for root in roots:
        if not root.exists():
            continue
        for p in sorted(root.rglob("*.vue")):
            findings.extend(check(p))

    if findings:
        print("Components used in a <script setup> template but never imported.")
        print("Vue logs this as a WARNING and renders nothing in their place:")
        print()
        for f in findings:
            print(f"  {f}")
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
