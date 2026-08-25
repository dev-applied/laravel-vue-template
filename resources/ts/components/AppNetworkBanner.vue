<template>
  <v-banner
    v-if="!online"
    color="warning"
    icon="wifi_off"
    sticky
    :lines="lines"
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
  computed: {
    /**
     * Two lines on phones, one everywhere else.
     *
     * `lines` sets a -webkit-line-clamp, and at 390px this message needs
     * two lines, so clamping to one renders "You're offline. Some actions
     * may not work…" — the half that tells you what to do about it is the
     * half that gets cut.
     */
    lines(): "one" | "two" {
      return this.$vuetify.display.xs ? "two" : "one"
    },
  },
})
</script>

<style lang="scss" scoped></style>
