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
 *
 * Impersonation keeps a SECOND slot. Starting an impersonation overwrites
 * the caller's own token with the impersonation one, and the backend has no
 * way to hand the original back afterwards — it only ever deletes the
 * impersonation token. Without somewhere to put it, "stop impersonating"
 * leaves the browser holding a token the server just destroyed, and the next
 * request 401s the admin out of the app entirely. So the original is stashed
 * before the swap and restored after, and it is persisted rather than held in
 * memory so a page reload mid-impersonation does not strand it.
 */

const TOKEN_KEY = "auth.token"
const IMPERSONATOR_TOKEN_KEY = "auth.impersonator_token"

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

/**
 * Park the current token as the impersonator's, before an impersonation token
 * takes its place. No-op when there is nothing signed in to park.
 */
export async function stashImpersonatorToken(): Promise<void> {
  if (inMemoryToken === null) {
    return
  }
  const storage = useStorage()

  // Never overwrite an existing stash. Impersonating while ALREADY
  // impersonating would otherwise park the impersonation token over the real
  // person's, and stopping would return them to the previous impersonated user
  // rather than to themselves — with their own token gone for good. Keeping the
  // first one means stop always goes back to whoever actually signed in.
  if (await storage.get(IMPERSONATOR_TOKEN_KEY)) {
    return
  }

  await storage.set(IMPERSONATOR_TOKEN_KEY, inMemoryToken)
}

/**
 * Read the stashed impersonator token and clear the slot in one motion.
 *
 * Read-and-clear rather than a plain getter: whether the caller manages to
 * restore it or not, the token must not stay in storage for a later session to
 * find. Returns null when no impersonation is in progress.
 */
export async function takeImpersonatorToken(): Promise<string | null> {
  const storage = useStorage()
  const token = await storage.get(IMPERSONATOR_TOKEN_KEY)
  await storage.remove(IMPERSONATOR_TOKEN_KEY)

  return token
}

/** Drop the stash without restoring it. Called on logout. */
export async function clearImpersonatorToken(): Promise<void> {
  const storage = useStorage()
  await storage.remove(IMPERSONATOR_TOKEN_KEY)
}
