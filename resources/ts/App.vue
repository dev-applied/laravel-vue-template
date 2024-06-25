<template>
  <component
    :is="layout"
    v-if="layout"
  >
    <router-view />
  </component>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
export default defineComponent({
  head() {
    return {
      titleTemplate: "%s | " + import.meta.env.VITE_APP_NAME,
      title: this.$route?.meta?.title || this.$route?.name || undefined
    }
  },
  computed: {
    layout(): string | null{
      if (!this.$route) return null
      return this.$route.meta?.layout ? this.$route.meta.layout + "Layout" : "EmptyLayout"
    }
  }
})
</script>
