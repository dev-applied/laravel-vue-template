<template>
  <v-row
    v-if="text && link"
    class="admin-layout__container__back-container mt-2"
    no-gutters
  >
    <router-link
      :to="typeof link === 'string' ? { name: link } : link"
      class="admin-layout__container__back-container__back-link"
    >
      <v-icon
        class="mr-1 mb-1"
        color="secondary"
        size="24"
      >
        keyboard_backspace
      </v-icon>
      <span>Back to {{ text }}</span>
    </router-link>
  </v-row>
</template>

<script lang="ts">
import { backButton } from "@/plugins/backButton/index"
import { defineComponent } from "vue"
import type { RouteLocationRaw } from "vue-router"

export default defineComponent({
  data() {
    return {
      link: null as string | RouteLocationRaw | null,
      text: null
    }
  },
  created() {
    backButton.registerInstance(this)
  },
  beforeUnmount() {
    backButton.unregisterInstance(this)
  },
  methods: {
    navigate() {
      // The button renders only when a link is set, but `link` is nullable in
      // the plugin state and router.push(null) is a runtime error rather than a
      // no-op, so the guard is real rather than a type formality.
      if (!this.link) return

      this.$router.push(this.link)
    }
  }
})
</script>

<style lang="scss" scoped>
.back-container {
  // A faint tint of the surface's own foreground, so the chip stays a subtle
  // step off the page on any theme instead of a fixed near-white.
  background: rgba(var(--v-theme-on-surface), 0.04);
  width: fit-content;
  cursor: pointer;
}
</style>
