<script lang="ts">
import {defineComponent} from "vue"
import {useRealtime} from "@modules/Realtime/resources/ts/composables/useRealtime"

/**
 * Says so when live updates have stopped arriving.
 *
 * The failure this exists for is silent: a dropped socket looks exactly like
 * "nothing has happened yet", so a user keeps reading a screen that stopped
 * updating twenty minutes ago and believes it.
 *
 * `disabled` is not shown. An environment with no realtime server configured is
 * not broken, and telling every user about it is noise.
 */
export default defineComponent({
  name: "AppConnectionBanner",
  setup() {
    return useRealtime()
  },
  computed: {
    visible(): boolean {
      return this.state.connection === "unavailable"
    },
  },
})
</script>

<template>
  <v-alert
    v-show="visible"
    type="warning"
    variant="tonal"
    rounded="0"
    density="compact"
    class="px-4"
    role="status"
    aria-live="polite"
  >
    Live updates have stopped. This page may be out of date — reload to catch up.
  </v-alert>
</template>
