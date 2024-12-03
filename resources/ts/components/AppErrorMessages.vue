<template>
  <div
    v-if="messages && messages.length > 0"
    class="app-error-messages"
  >
    <v-row
      v-for="(message, index) in messages"
      :key="index + '-message'"
      no-gutters
    >
      <v-alert
        :type="message.type.toLowerCase()"
        closable
        class="mb-1"
      >
        {{ message.message }}
      </v-alert>
    </v-row>
  </div>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import { mapState } from "pinia"
import { useAppStore } from "@/stores/app"
import { useLayout } from "vuetify"

export default defineComponent({
  async setup() {
    const {mainRect, layoutIsReady} = useLayout()
    await layoutIsReady
    const topOffset = `${mainRect.value.top}px`

    return {
      topOffset
    }
  },
  computed: {
    ...mapState(useAppStore, ["messages"]),
  }
})
</script>

<style scoped lang="scss">
.app-error-messages {
  position: fixed;
  top: calc(v-bind(topOffset) + 12px);
  left: calc(100% / 2);
  transform: translate(-50%, 0);
  max-width: 600px;
  width: 100%;
  z-index: 99000;
}
</style>
