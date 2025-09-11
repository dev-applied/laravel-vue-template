import {defineStore} from "pinia"

export const useAppStore = defineStore("app", {
  state: () => {
    return {
      showNavigation: false as boolean,
    }
  }
})
