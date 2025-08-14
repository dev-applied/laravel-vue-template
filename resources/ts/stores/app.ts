import { defineStore } from "pinia"

export const useAppStore = defineStore("app", {
  state: () => {
    return {
      messages: [] as { type: "Success" | "Error" | "Warning", message: string }[],
      showNavigation: false as boolean,
    }
  },
  actions: {
    addError(error: string, timeout: number = 10000) {
      this.messages.push({ type: "Error", message: error })
      setTimeout(() => {
        this.messages.splice(0, 1)
      }, timeout)
    },
    addSuccess(message: string, timeout: number = 5000) {
      this.messages.push({ type: "Success", message })
      if (timeout > 0) {
        setTimeout(() => {
          this.messages.splice(0, 1)
        }, timeout)
      }
    },
    addWarning(message: string, timeout: number = 10000) {
      this.messages.push({ type: "Warning", message })
      if (timeout > 0) {
        setTimeout(() => {
          this.messages.splice(0, 1)
        }, timeout)
      }
    },
  }
})
