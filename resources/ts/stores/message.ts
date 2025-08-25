import {defineStore} from "pinia"

enum MessageType {
  "Success" = "success",
  "Error" = "error",
  "Warning" = "warning",
}

type Message = {
  type: MessageType
  message: string
}

export const useMessageStore = defineStore("message", {
  state: () => {
    return {
      messages: [] as Message[],
    }
  },
  actions: {
    addMessage(
      type: MessageType,
      message: string,
      timeout: number = 10000
    ) {
      this.messages.push({
        type,
        message,
      })
      setTimeout(() => {
        this.messages.splice(0, 1)
      }, timeout)
    },
    addError(message: string, timeout?: number) {
      this.addMessage(MessageType.Error, message, timeout)
    },
    addSuccess(message: string, timeout?: number) {
      this.addMessage(MessageType.Success, message, timeout)
    },
    addWarning(message: string, timeout?: number) {
      this.addMessage(MessageType.Warning, message, timeout)
    },
  }
})
