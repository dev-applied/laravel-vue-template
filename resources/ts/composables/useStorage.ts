import { Capacitor } from "@capacitor/core"
import { Preferences } from "@capacitor/preferences"

/**
 * Cross-platform key/value storage.
 *
 *   - On native (iOS/Android via Capacitor): backed by @capacitor/preferences,
 *     which persists across app launches and is the recommended store for auth
 *     tokens (WebView localStorage can be cleared by the OS).
 *   - On web: backed by window.localStorage.
 *
 * API is async in both cases so feature code never has to branch.
 *
 * Usage:
 *
 *   const storage = useStorage()
 *   await storage.set("auth.token", token)
 *   const t = await storage.get("auth.token")
 *   await storage.remove("auth.token")
 */
export interface Storage {
  get(key: string): Promise<string | null>
  set(key: string, value: string): Promise<void>
  remove(key: string): Promise<void>
  clear(): Promise<void>
  keys(): Promise<string[]>
}

const nativeStorage: Storage = {
  async get(key)         { return (await Preferences.get({ key })).value },
  async set(key, value)  { await Preferences.set({ key, value }) },
  async remove(key)      { await Preferences.remove({ key }) },
  async clear()          { await Preferences.clear() },
  async keys()           { return (await Preferences.keys()).keys },
}

const webStorage: Storage = {
  async get(key)         { return window.localStorage.getItem(key) },
  async set(key, value)  { window.localStorage.setItem(key, value) },
  async remove(key)      { window.localStorage.removeItem(key) },
  async clear()          { window.localStorage.clear() },
  async keys()           { return Object.keys(window.localStorage) },
}

export function useStorage(): Storage {
  return Capacitor.isNativePlatform() ? nativeStorage : webStorage
}
