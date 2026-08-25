import {defineStore} from "pinia"
import {$http, type AxiosResponse} from "@/plugins/axios"
import {clearAuthToken, setAuthToken} from "@/plugins/authToken"

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

export interface LoginForm {
  email: string
  password: string
}

export const useUserStore = defineStore("user", {
  state: () => {
    return {
      user: null as App.Models.AuthUser | null
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
      await clearAuthToken()

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
        data: {user}
      }: AxiosResponse<{ user: App.Models.AuthUser }> = await $http.get("/auth").catch((e) => e)
      this.user = user
    },
    async impersonate(userId: number) {
      const response: AxiosResponse<{ access_token: string }> = await $http
        .post("/auth/impersonate", {
          user_id: userId
        })
        .catch((e) => e)

      if (response.data.access_token) {
        await setAuthToken(response.data.access_token)
        await this.loadUser(true)
      }

      return response
    },
    async stopImpersonating() {
      const response: AxiosResponse<{ access_token: string }> = await $http
        .delete("/auth/stop-impersonating")
        .catch((e) => e)

      if (response.data.access_token) {
        await setAuthToken(response.data.access_token)
        await this.loadUser(true)
      }

      return response
    },
  }
})
