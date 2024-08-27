<template>
  <v-row
    v-if="messages && messages.length > 0"
    class="app-error-messages"
  >
    <v-col>
      <v-alert
        v-for="(message, index) in messages"
        :key="index + '-message'"
        class="mb-2"
        :type="message.type.toLowerCase()"
        closable
      >
        {{ message.message }}
      </v-alert>
    </v-col>
  </v-row>
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
