import { defineStore } from "pinia"

export const useAppStore = defineStore("app", {
  state: () => {
    return {
      messages: [] as { type: "Success" | "Error", message: String }[]
    }
  },
  actions: {
    addError(error: String, timeout: number = 5000) {
      this.messages.push({ type: "Error", message: error })
      window.scrollTo(0, 0)
      setTimeout(() => {
        this.messages.splice(0, 1)
      }, timeout)
    },
    addSuccess(message: String, timeout: number = 5000) {
      this.messages.push({ type: "Success", message })
      window.scrollTo(0, 0)
      if (timeout > 0) {
        setTimeout(() => {
          this.messages.splice(0, 1)
        }, timeout)
      }
    }
  }
})
