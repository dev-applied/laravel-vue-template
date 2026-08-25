import type {App} from "vue"
import {onBeforeLogout} from "@/stores/user"
import {$http} from "@/plugins/axios"

/**
 * SAML Single Logout, hooked into the app's normal sign-out.
 *
 * Without this the module is only half a Single Logout: the local tokens go,
 * the IdP session stays, and the next sign-in silently succeeds without ever
 * asking for credentials — which looks like sign-out is broken, and on a shared
 * machine means the next person is still signed in as the last one.
 *
 * `/auth/saml/logout` answers `{url: null}` for anyone who signed in with a
 * password, or when the IdP advertises no SLO endpoint. That is a complete
 * answer, not a failure — there is simply nowhere to send the browser, and
 * local sign-out proceeds exactly as it always did.
 */
 
export default function install(_app: App): void {
  onBeforeLogout(async () => {
    // Runs while the token is still live; the endpoint is auth:sanctum.
    const response = await $http.post<{ url: string | null }>("/auth/saml/logout").catch((e) => e)

    return response?.data?.url ?? undefined
  })
}
