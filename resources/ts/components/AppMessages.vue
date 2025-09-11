<template>
  <div
    class="app-messages"
  >
    <v-row
      v-for="(message, index) in messages"
      :key="index + '-message'"
      no-gutters
    >
      <v-alert
        :type="message.type"
        class="mb-1"
        closable
      >
        {{ message.message }}
      </v-alert>
    </v-row>
  </div>
</template>

<script lang="ts">
import {defineComponent} from "vue"
import {mapState} from "pinia"
import {useLayout} from "vuetify"
import {useMessageStore} from "@/stores/message.ts"

export default defineComponent({
  setup() {
    const {mainRect} = useLayout()

    return {
      mainRect
    }
  },
  computed: {
    ...mapState(useMessageStore, ["messages"]),
    topOffset(): string {
      return `${this.mainRect.top}px`
    }
  },
})
</script>

<style
  lang="scss"
  scoped
>
.app-messages {
  position: fixed;
  top: calc(v-bind(topOffset) + 12px);
  left: calc(100% / 2);
  transform: translate(-50%, 0);
  max-width: 600px;
  width: 100%;
  z-index: 99000;
}
</style>
