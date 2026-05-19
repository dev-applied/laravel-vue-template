<template>
  <v-banner
    v-if="!online"
    color="warning"
    icon="wifi_off"
    sticky
    lines="one"
    density="compact"
  >
    <v-banner-text>
      {{ message }}
    </v-banner-text>
  </v-banner>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import { useCapacitor } from "@/composables/useCapacitor"

export default defineComponent({
  name: "AppNetworkBanner",
  props: {
    message: {
      type: String,
      default: "You're offline. Some actions may not work until you reconnect.",
    },
  },
  setup() {
    const { online } = useCapacitor()
    return { online }
  },
})
</script>

<style lang="scss" scoped></style>
