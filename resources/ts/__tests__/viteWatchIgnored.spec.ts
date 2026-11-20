import {mkdtempSync, rmSync} from "node:fs"
import {tmpdir} from "node:os"
import {join} from "node:path"
import {fileURLToPath} from "node:url"
import {resolveConfig} from "vite"
import {afterEach, describe, expect, it} from "vitest"
import {rootOnlyWatchIgnored} from "../../../vite.config"

// Parentheses and spaces are glob syntax; a root-only exclusion must not depend on the path.
const root = "/srv/My Projects (2)/app"
const roots = [root, `${root}/`]

describe("dev server watch exclusions", () => {
  const env = {...process.env}
  afterEach(() => {
    process.env = {...env}
  })

  it.each([
    "vendor",
    "vendor/laravel/framework/src/Foundation/Application.php",
    "storage/logs/laravel.log",
    "bootstrap/cache/config.php",
    "public",
    "public/hot",
    "ios/App/App/public/index.html",
    "android/app/build.gradle",
    "capacitor-cordova-ios-plugins/sources/x.m",
    "capacitor-cordova-android-plugins/build.gradle",
    "dist/index.html",
    ".modules-src",
    ".modules-src/Example/resources/ts/pages/ExamplePage.vue",
    ".worktrees/old/vendor/autoload.php",
    ".claude/worktrees/agent/vendor/autoload.php",
    ".codex/worktrees/agent/vendor/autoload.php",
    ".agents/run/vendor/autoload.php",
    ".junie/guidelines.md",
    ".idea/workspace.xml",
    ".vscode/settings.json",
    ".fleet/settings.json",
    "coverage/index.html",
    "playwright-report/index.html",
    ".playwright-mcp/page.png",
    ".phpunit.cache/test-results",
  ])("ignores %s at the project root", (path) => {
    for (const r of roots) expect(rootOnlyWatchIgnored(r)(`${root}/${path}`)).toBe(true)
  })

  it.each([
    "resources/ts/pages/public/PublicPage.vue",
    "resources/views/vendor/mail/html/message.blade.php",
    "resources/ts/types/vendor/shims.d.ts",
    "resources/ts/dist/helper.ts",
    "modules/Example/resources/ts/pages/ExamplePage.vue",
    "routes/web.php",
    "publications/notes.ts",
  ])("watches %s", (path) => {
    for (const r of roots) expect(rootOnlyWatchIgnored(r)(`${root}/${path}`)).toBe(false)
  })

  it("leaves a sibling checkout and paths outside the root alone", () => {
    for (const r of roots) {
      expect(rootOnlyWatchIgnored(r)(`${root}-old/vendor/autoload.php`)).toBe(false)
      expect(rootOnlyWatchIgnored(r)("/srv/My Projects (2)/vendor/autoload.php")).toBe(false)
    }
  })

  it("follows the root Vite resolves, not the working directory", async () => {
    const elsewhere = mkdtempSync(join(tmpdir(), "watch-root-"))
    try {
      const configFile = fileURLToPath(new URL("../../../vite.config.ts", import.meta.url))
      const resolved = await resolveConfig({configFile, root: elsewhere, mode: "development"}, "serve")
      const ignored = resolved.server.watch?.ignored
      expect(ignored).toBeInstanceOf(Array)
      const matcher = (ignored as unknown[]).find((entry) => typeof entry === "function") as (path: string) => boolean
      expect(matcher).toBeTypeOf("function")

      expect(matcher(`${elsewhere}/vendor/autoload.php`)).toBe(true)
      expect(matcher(`${elsewhere}/resources/ts/pages/public/PublicPage.vue`)).toBe(false)
      expect(matcher(`${process.cwd()}/vendor/autoload.php`)).toBe(false)
    } finally {
      rmSync(elsewhere, {recursive: true, force: true})
    }
  })
})
