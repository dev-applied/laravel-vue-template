// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from "vitest"

// vi.mock() is hoisted above top-level `const`s, so any value referenced inside
// the factory must be created with vi.hoisted() to be initialized in time.
const { isNativePlatform, getPlatform, appGetInfo, getStatus, addListener } = vi.hoisted(() => {
  const isNativePlatform = vi.fn(() => false)
  const getPlatform      = vi.fn(() => "web" as "ios" | "android" | "web")
  const appGetInfo = vi.fn(async () => ({
    name:    "Test App",
    id:      "com.appliedimagination.test",
    build:   "1",
    version: "1.0.0",
  }))
  const getStatus  = vi.fn(async () => ({ connected: true, connectionType: "wifi" as const }))
  const addListener = vi.fn(async () => ({ remove: vi.fn(async () => {}) }))
  return { isNativePlatform, getPlatform, appGetInfo, getStatus, addListener }
})

vi.mock("@capacitor/core", () => ({
  Capacitor: { isNativePlatform, getPlatform },
}))

vi.mock("@capacitor/app", () => ({
  App: { getInfo: appGetInfo },
}))

vi.mock("@capacitor/network", () => ({
  Network: { getStatus, addListener },
}))

import { useCapacitor } from "../useCapacitor"

describe("useCapacitor", () => {
  beforeEach(() => {
    vi.clearAllMocks()
    isNativePlatform.mockReturnValue(false)
    getPlatform.mockReturnValue("web")
  })

  it("returns isNative=false and platform='web' on a browser", () => {
    const { isNative, platform } = useCapacitor()
    expect(isNative.value).toBe(false)
    expect(platform.value).toBe("web")
  })

  it("returns isNative=true and platform='ios' when natively bridged", () => {
    isNativePlatform.mockReturnValue(true)
    getPlatform.mockReturnValue("ios")
    const { isNative, platform } = useCapacitor()
    expect(isNative.value).toBe(true)
    expect(platform.value).toBe("ios")
  })

  it("appInfo() returns null on web without calling App.getInfo", async () => {
    const { appInfo } = useCapacitor()
    const info = await appInfo()
    expect(info).toBeNull()
    expect(appGetInfo).not.toHaveBeenCalled()
  })

  it("subscribes to network status changes", () => {
    useCapacitor()
    expect(addListener).toHaveBeenCalledWith("networkStatusChange", expect.any(Function))
  })
})
