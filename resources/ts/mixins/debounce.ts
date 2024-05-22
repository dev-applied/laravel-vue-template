import debounce from 'lodash/debounce'
import {defineComponent} from 'vue'

export default defineComponent({
  methods: {
    debouncer: debounce(function (callback) {
      callback()
    }, 1000)
  }
})
