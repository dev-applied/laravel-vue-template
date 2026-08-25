import type {LoginForm} from "@/stores/user"
import {useUserStore} from "@/stores/user"
import type {AxiosResponse} from "@/plugins/axios"
import type {App} from "vue"

export interface Auth {
  user: App.Models.AuthUser | null
  hasPermission: (permission: string) => boolean
  hasAllPermissions: (permissions: string[] | string[][]) => boolean
  hasAnyPermissions: (permissions: string[] | string[][]) => boolean
  login: (form: LoginForm) => Promise<AxiosResponse<{ access_token: string }>>
  loadUser: (force?: boolean) => Promise<void>
  logout: () => Promise<void>
  impersonate: (userId: number) => Promise<AxiosResponse<{ access_token: string }>>
  stopImpersonating: () => Promise<AxiosResponse<{ message: string }>>
  loggedIn: boolean
  /** True while `user` is somebody being impersonated. */
  impersonating: boolean
  /** Display name of whoever started the impersonation, when known. */
  impersonator: string | null
}

export const $auth: Auth = {
  get user(): App.Models.AuthUser | null {
    const userStore = useUserStore()
    return userStore.user
  },
  set user(user: App.Models.AuthUser | null) {
    const userStore = useUserStore()
    userStore.user = user
  },
  hasPermission(permission: string): boolean {
    return this.hasAnyPermissions([permission])
  },
  hasAnyPermissions(permissions: string[] | string[][]): boolean {
    const granted = getPermissionsFromUser(this.user)

    return permissions.flat().some((p) => granted.includes(p))
  },
  hasAllPermissions(permissions: string[] | string[][]): boolean {
    const granted = getPermissionsFromUser(this.user)

    return permissions.flat().every((p) => granted.includes(p))
  },
  async login(form: LoginForm) {
    const userStore = useUserStore()
    return await userStore.login(form)
  },
  async loadUser(force: boolean = false) {
    const userStore = useUserStore()
    return await userStore.loadUser(force)
  },
  logout() {
    const userStore = useUserStore()
    return userStore.logout()
  },
  get loggedIn() {
    return !!this.user
  },
  get impersonating(): boolean {
    return useUserStore().impersonating
  },
  get impersonator(): string | null {
    return useUserStore().impersonator
  },
  async impersonate(userId: number) {
    const userStore = useUserStore()
    return await userStore.impersonate(userId)
  },
  async stopImpersonating() {
    const userStore = useUserStore()
    return await userStore.stopImpersonating()
  },
}

/**
 * Permission names granted to the user.
 *
 * `all_permissions` is contributed by the RolesPermissions module (it is
 * spatie's getAllPermissions() shape: [{name: string}, ...]). A project without
 * that module has no such key, so this returns [] and every permission check
 * FAILS CLOSED rather than throwing — which is what the commented-out version
 * used to do, since Authorization.ts calls these on every gated navigation.
 */
function getPermissionsFromUser(user: App.Models.AuthUser | null): string[] {
  const granted = (user as { all_permissions?: { name: string }[] } | null)?.all_permissions

  if (!Array.isArray(granted)) {
    return []
  }

  return [...new Set(granted.map((p) => p.name))]
}

export default {
  install(app: App) {
    app.config.globalProperties.$auth = $auth
  }
}
