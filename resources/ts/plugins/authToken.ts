import { useStorage } from "@/composables/useStorage"

/**
 * In-memory auth-token cache backed by useStorage (Capacitor Preferences on
 * native, localStorage on web).
 *
 * Why a cache: axios request interceptors are sync; @capacitor/preferences is
 * async. The cache lets the interceptor read synchronously while persisting
 * survives across launches even on iOS WebView (which can evict localStorage).
 *
 * Lifecycle:
 *   - main.ts awaits `loadAuthToken()` before mounting Vue.
 *   - user store calls `setAuthToken(t)` on login/impersonate and
 *     `clearAuthToken()` on logout.
 *   - axios interceptor reads `getAuthToken()` sync.
 */

const TOKEN_KEY = "auth.token"

let inMemoryToken: string | null = null

export function getAuthToken(): string | null {
  return inMemoryToken
}

export async function loadAuthToken(): Promise<void> {
  const storage = useStorage()
  inMemoryToken = await storage.get(TOKEN_KEY)
}

export async function setAuthToken(token: string): Promise<void> {
  inMemoryToken = token
  const storage = useStorage()
  await storage.set(TOKEN_KEY, token)
}

export async function clearAuthToken(): Promise<void> {
  inMemoryToken = null
  const storage = useStorage()
  await storage.remove(TOKEN_KEY)
}
