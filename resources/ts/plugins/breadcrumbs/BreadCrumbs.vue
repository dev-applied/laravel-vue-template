<template>
  <v-breadcrumbs
    :items="items"
  >
    <v-breadcrumbs-item
      v-for="(item, i) in items"
      :key="i"
      :disabled="item.disabled"
      :to="typeof item.to === 'string' ? { name: item.to } : item.to"
      exact-path
    >
      <template v-if="item.icon">
        <v-icon>{{ item.icon }}</v-icon>
      </template>
      {{ item.text }}

      <template v-if="i < items.length - 1">
        <div class="ml-3 black--text">
          >
        </div>
      </template>
    </v-breadcrumbs-item>
  </v-breadcrumbs>
</template>

<script lang="ts">
import { defineComponent } from "vue"
import { breadCrumbs } from "@/plugins/breadcrumbs/index"

export default defineComponent({
  data() {
    return {
      items: [] as { icon?: string, text?: string, disabled?: boolean, to?: string }[]
    }
  },
  created() {
    breadCrumbs.registerInstance(this)
  },
  beforeDestroy() {
    breadCrumbs.unregisterInstance(this)
  }
})
</script>

<style lang="scss" scoped>
</style>
