<template>
  <v-breadcrumbs>
    <v-breadcrumbs-item
      v-for="(item, i) in items"
      :key="i"
      :disabled="item.disabled"
      :to="typeof item.to === 'string' ? { name: item.to } : item.to"
      exact
    >
      <template v-if="item.icon">
        <v-icon>{{ item.icon }}</v-icon>
      </template>
      {{ item.text }}

      <template v-if="i < items.length - 1">
        <div class="ml-3 text-black">
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
      items: [] as Breadcrumbs.Item[]
    }
  },
  created() {
    breadCrumbs.registerInstance(this)
  },
  beforeUnmount() {
    breadCrumbs.unregisterInstance(this)
  }
})
</script>

<style lang="scss" scoped>
</style>
