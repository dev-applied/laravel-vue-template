import {defineStore} from "pinia"
import {$http, type AxiosResponse} from "@/plugins/axios"
import {
  clearAuthToken,
  clearImpersonatorToken,
  getAuthToken,
  setAuthToken,
  stashImpersonatorToken,
  takeImpersonatorToken
} from "@/plugins/authToken"

/**
 * Something a module needs to do while the user is still signed in, on the way
 * out.
 *
 * The ordering is the whole point. A handler runs BEFORE the token is cleared,
 * because the work it does is usually an authenticated request — SAML Single
 * Logout has to POST for the IdP logout URL, and that call is rejected the
 * instant the token is gone. Returning a URL sends the browser there once
 * local sign-out has finished; returning nothing is the normal case.
 *
 * A handler that throws is swallowed on purpose: whatever it was doing, the
 * user asked to log out, and failing to reach a third party must never leave
 * them logged in here.
 */
export type LogoutHandler = () => Promise<string | void> | string | void

const logoutHandlers: LogoutHandler[] = []

/** Register a logout handler. Call from a module's `plugin.ts`. */
export function onBeforeLogout(handler: LogoutHandler): void {
  logoutHandlers.push(handler)
}

/**
 * Best available display name for a user.
 *
 * Not `full_name`: the model appends it and the API returns it, but the
 * Wayfinder-generated AuthUser type only carries real columns, so reading it
 * does not type-check. first_name/last_name are typed and always present.
 */
export function displayName(user: App.Models.AuthUser | null): string | null {
  if (user === null) {
    return null
  }
  const name = [user.first_name, user.last_name].filter(Boolean).join(" ").trim()

  return name === "" ? (user.email ?? null) : name
}

export interface LoginForm {
  email: string
  password: string
}

export const useUserStore = defineStore("user", {
  state: () => {
    return {
      user: null as App.Models.AuthUser | null,
      /**
       * True while `user` is somebody being impersonated rather than the person
       * who signed in. Reported by GET /auth off the token's abilities, so it
       * survives a reload — the browser cannot work it out on its own.
       */
      impersonating: false,
      /** Display name of whoever started the impersonation, for the banner. */
      impersonator: null as string | null
    }
  },
  actions: {
    async login({email, password}: LoginForm) {
      const response: AxiosResponse<{ access_token: string }> = await $http
        .post("/auth", {
          email,
          password
        })
        .catch((e) => e)

      if (response.data.access_token) {
        await this.setToken(response.data.access_token)
        await this.loadUser()
      }

      return response
    },
    async setToken(token: string) {
      await setAuthToken(token)
      await this.loadUser(true)
    },
    async logout() {
      // Handlers first, while the token is still valid — see LogoutHandler.
      let redirect: string | undefined

      for (const handler of logoutHandlers) {
        try {
          redirect = (await handler()) || redirect
        } catch (e) {
          // Never block sign-out on a handler. Reported, not surfaced: the
          // user asked to leave and is leaving either way.
          console.error("[logout handler]", e)
        }
      }

      await $http.delete("/auth").catch((e) => e)
      this.user = null
      this.impersonating = false
      this.impersonator = null
      await clearAuthToken()
      // Logging out mid-impersonation would otherwise leave the impersonator's
      // own bearer token sitting in storage after they have signed out.
      await clearImpersonatorToken()

      // Last, and only after local state is gone. A redirect that fired first
      // would leave a live token behind on a navigation that may not return.
      if (typeof redirect === "string" && redirect !== "") {
        window.location.href = redirect
      }
    },
    async loadUser(force: boolean = false) {
      if (this.user && !force) {
        return
      }
      const {
        data: {user, impersonating}
      }: AxiosResponse<{
        user: App.Models.AuthUser
        impersonating?: boolean
      }> = await $http.get("/auth").catch((e) => e)
      this.user = user
      this.impersonating = impersonating === true
    },
    async impersonate(userId: number) {
      // Whoever is signed in right now is the one we have to be able to get
      // back to. Park their token first: the line below overwrites it, and the
      // server never hands it back.
      const impersonator = displayName(this.user)
      await stashImpersonatorToken()

      const response: AxiosResponse<{ access_token: string }> = await $http
        .post("/auth/impersonate", {
          user_id: userId
        })
        .catch((e) => e)

      if (response.data?.access_token) {
        await setAuthToken(response.data.access_token)
        await this.loadUser(true)
        this.impersonator = impersonator
      } else {
        // 422 (already that user) or any failure: nothing was swapped, so the
        // stash is stale and must not outlive the attempt.
        await clearImpersonatorToken()
      }

      return response
    },
    async stopImpersonating() {
      const response: AxiosResponse<{ message: string }> = await $http
        .delete("/auth/stop-impersonating")
        .catch((e) => e)

      // The impersonation token is gone server-side whether or not the call
      // reported success, so the browser must stop presenting it either way.
      const original = await takeImpersonatorToken()

      if (original !== null && original !== getAuthToken()) {
        await setAuthToken(original)
        await this.loadUser(true)
      } else {
        // Nothing to go back to — signed in directly with an impersonation
        // token, or the stash was lost. Sign out locally rather than leaving a
        // destroyed token in place to 401 on the next request.
        this.user = null
        await clearAuthToken()
      }

      this.impersonating = false
      this.impersonator = null

      return response
    },
  }
})
