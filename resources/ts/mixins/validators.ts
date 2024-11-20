import {defineComponent} from "vue"
import {rules} from "@/composables/useValidators"

export default defineComponent({
  computed: {
    rules() {
      return rules
    }
  }
})
