import { defineStore } from 'pinia'
import type { AuthUser } from '@/types'
import axios from 'axios'
import type { AxiosResponse } from '@/plugins/axios'

export interface LoginForm {
  email: string
  password: string
}

export const useUserStore = defineStore('user', {
  state: () => {
    return {
      user: null as AuthUser | null
    }
  },
  actions: {
    async login({ email, password }: LoginForm) {
      const response: AxiosResponse<{ user: AuthUser }> = await axios
        .post('/auth', {
          email,
          password
        })
        .catch((e) => e)

      if (response.data.user) {
        this.user = response.data.user
      }

      return response
    },
    async logout() {
      await axios.delete('/auth').catch((e) => e)
      this.user = null
    },
    async loadUser() {
      if (this.user) {
        return
      }
      const {
        data: { user }
      }: AxiosResponse<{ user: AuthUser }> = await axios.get('/auth').catch((e) => e)
      this.user = user
    }
  }
})
