<template>
  <component
    :is="layout"
    v-if="layout"
  >
    <router-view />
  </component>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import type { MetaInfo } from "vue-meta"

export default defineComponent({
  metaInfo(): MetaInfo {

    return {
      titleTemplate: "%s | " + import.meta.env.VITE_APP_NAME,
      title: this.$route?.meta?.title || this.$route?.name || undefined
    }
  },
  computed: {
    layout() {
      if (!this.$route) return null
      return this.$route.meta?.layout ? this.$route.meta.layout + "Layout" : "EmptyLayout"
    }
  }
})
</script>
