import { defineStore } from "pinia"

export const useAppStore = defineStore("app", {
  state: () => {
    return {
      messages: [] as { type: "Success" | "Error" | "Warning", message: String }[],
      showNavigation: false as boolean,
    }
  },
  actions: {
    addError(error: String, timeout: number = 10000) {
      this.messages.push({ type: "Error", message: error })
      setTimeout(() => {
        this.messages.splice(0, 1)
      }, timeout)
    },
    addSuccess(message: String, timeout: number = 5000) {
      this.messages.push({ type: "Success", message })
      if (timeout > 0) {
        setTimeout(() => {
          this.messages.splice(0, 1)
        }, timeout)
      }
    },
    addWarning(message: String, timeout: number = 10000) {
      this.messages.push({ type: "Warning", message })
      if (timeout > 0) {
        setTimeout(() => {
          this.messages.splice(0, 1)
        }, timeout)
      }
    },
  }
})
