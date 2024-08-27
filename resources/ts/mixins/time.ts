import moment from "moment"

import { defineComponent } from "vue"
import useTime from "@/composables/useTime"

export default defineComponent({
  setup() {
    return useTime()
  }
})
