import { defineStore } from "pinia"
import axios from "axios"
import type { AxiosResponse } from "@/plugins/axios"

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
    async login({ email, password }: LoginForm) {
      const response: AxiosResponse<{ access_token: string }> = await axios
        .post("/auth", {
          email,
          password
        })
        .catch((e) => e)

      if (response.data.access_token) {
        localStorage.setItem("token", response.data.access_token)
        await this.loadUser()
      }

      return response
    },
    async logout() {
      await axios.delete("/auth").catch((e) => e)
      this.user = null
      localStorage.removeItem('token')
    },
    async loadUser() {
      if (this.user) {
        return
      }
      const {
        data: { user }
      }: AxiosResponse<{ user: App.Models.AuthUser }> = await axios.get("/auth").catch((e) => e)
      this.user = user
    }
  }
})
