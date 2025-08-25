import {defineStore} from "pinia"
import {$http, type AxiosResponse} from "@/plugins/axios"

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
      localStorage.setItem("token", token)
      await this.loadUser(true)
    },
    async logout() {
      await $http.delete("/auth").catch((e) => e)
      this.user = null
      localStorage.removeItem('token')
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
        localStorage.setItem("token", response.data.access_token)
        await this.loadUser(true)
      }

      return response
    },
    async stopImpersonating() {
      const response: AxiosResponse<{ access_token: string }> = await $http
        .delete("/auth/stop-impersonating")
        .catch((e) => e)

      if (response.data.access_token) {
        localStorage.setItem("token", response.data.access_token)
        await this.loadUser(true)
      }

      return response
    },
  }
})
