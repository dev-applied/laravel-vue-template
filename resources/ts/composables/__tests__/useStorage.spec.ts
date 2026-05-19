// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from "vitest"

// vi.mock() is hoisted above top-level `const`s, so any value referenced inside
// the factory must be created with vi.hoisted() to be initialized in time.
const { isNativePlatform, prefsStore, prefGet, prefSet, prefRemove, prefClear, prefKeys } = vi.hoisted(() => {
  const isNativePlatform = vi.fn(() => false)
  const prefsStore = new Map<string, string>()
  const prefGet    = vi.fn(async ({ key }: { key: string }) => ({ value: prefsStore.get(key) ?? null }))
  const prefSet    = vi.fn(async ({ key, value }: { key: string; value: string }) => { prefsStore.set(key, value) })
  const prefRemove = vi.fn(async ({ key }: { key: string }) => { prefsStore.delete(key) })
  const prefClear  = vi.fn(async () => { prefsStore.clear() })
  const prefKeys   = vi.fn(async () => ({ keys: Array.from(prefsStore.keys()) }))
  return { isNativePlatform, prefsStore, prefGet, prefSet, prefRemove, prefClear, prefKeys }
})

vi.mock("@capacitor/core", () => ({
  Capacitor: { isNativePlatform },
}))

vi.mock("@capacitor/preferences", () => ({
  Preferences: { get: prefGet, set: prefSet, remove: prefRemove, clear: prefClear, keys: prefKeys },
}))

import { useStorage } from "../useStorage"

describe("useStorage", () => {
  beforeEach(() => {
    window.localStorage.clear()
    prefsStore.clear()
    vi.clearAllMocks()
  })

  describe("on web (isNativePlatform = false)", () => {
    beforeEach(() => { isNativePlatform.mockReturnValue(false) })

    it("set/get/remove uses window.localStorage", async () => {
      const storage = useStorage()
      await storage.set("a", "1")
      expect(window.localStorage.getItem("a")).toBe("1")
      expect(await storage.get("a")).toBe("1")
      await storage.remove("a")
      expect(window.localStorage.getItem("a")).toBeNull()
      expect(prefSet).not.toHaveBeenCalled()
    })

    it("get of unknown key returns null (not undefined)", async () => {
      expect(await useStorage().get("missing")).toBeNull()
    })
  })

  describe("on native (isNativePlatform = true)", () => {
    beforeEach(() => { isNativePlatform.mockReturnValue(true) })

    it("set/get/remove uses @capacitor/preferences", async () => {
      const storage = useStorage()
      await storage.set("auth.token", "abc")
      expect(prefSet).toHaveBeenCalledWith({ key: "auth.token", value: "abc" })
      expect(await storage.get("auth.token")).toBe("abc")
      await storage.remove("auth.token")
      expect(prefRemove).toHaveBeenCalledWith({ key: "auth.token" })
    })

    it("does NOT touch localStorage", async () => {
      await useStorage().set("a", "1")
      expect(window.localStorage.getItem("a")).toBeNull()
    })
  })
})
