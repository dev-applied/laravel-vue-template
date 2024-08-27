import { defineComponent } from "vue"
import useValidators from "@/composables/useValidators"

export default defineComponent({
  setup() {
    return useValidators()
  }
})
