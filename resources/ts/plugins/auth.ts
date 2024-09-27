import type { LoginForm } from "@/stores/user"
import { useUserStore } from "@/stores/user"
import type { AxiosResponse } from "@/plugins/axios"
import type { App } from "vue"

export interface Auth {
  user: App.Models.AuthUser | null
  hasPermission: (permission: string) => boolean
  hasAllPermissions: (permissions: string[] | string[][]) => boolean
  hasAnyPermissions: (permissions: string[] | string[][]) => boolean
  login: (form: LoginForm) => Promise<AxiosResponse<{ access_token: string }>>
  loadUser: (force?: boolean) => Promise<void>
  logout: () => Promise<void>
  loggedIn: boolean
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
    return permissions.flat().some((p) => getPermissionsFromUser(this.user).includes(p))
  },
  hasAllPermissions(permissions: string[] | string[][]): boolean {
    return permissions.flat().every((p) => getPermissionsFromUser(this.user).includes(p))
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
  }
}

function getPermissionsFromUser(user: App.Models.AuthUser | null) {
  let permissions: string[] = []

  if (!user) {
    return permissions
  }

  permissions = permissions.concat(user.all_permissions.map((p) => p.name))

  permissions = [...new Set(permissions)]

  return permissions
}

export default {
  install(app: App) {
    app.config.globalProperties.$auth = $auth
  }
}
