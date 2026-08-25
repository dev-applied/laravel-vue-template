<template>
  <v-card class="h-100">
    <v-card-title class="d-flex align-center ga-2">
      <v-icon
        v-if="widget.icon"
        :color="widget.color || undefined"
        :icon="widget.icon"
        size="20"
      />
      <span>{{ widget.label }}</span>
      <v-chip
        v-if="total"
        class="ms-1"
        density="comfortable"
        :color="widget.color || 'primary'"
        size="small"
      >
        {{ total }}
      </v-chip>
    </v-card-title>

    <v-divider />

    <v-card-text
      v-if="widget.error"
      class="text-medium-emphasis"
    >
      {{ widget.error }}
    </v-card-text>

    <v-list
      v-else-if="items.length"
      density="comfortable"
      lines="two"
    >
      <v-list-item
        v-for="item in items"
        :key="item.id"
        :subtitle="item.subtitle || undefined"
        :title="item.title"
        :to="item.url || undefined"
      >
        <template
          v-if="item.badge"
          #append
        >
          <v-chip
            :color="item.color || undefined"
            density="comfortable"
            size="small"
          >
            {{ item.badge }}
          </v-chip>
        </template>
      </v-list-item>
    </v-list>

    <v-card-text
      v-else
      class="text-center text-medium-emphasis py-8"
    >
      <v-icon
        class="mb-2"
        icon="check_circle_outline"
        size="32"
      />
      <div>Nothing needs attention.</div>
    </v-card-text>
  </v-card>
</template>

<script lang="ts">
import {defineComponent, type PropType} from "vue"
import type {DashboardWidget, QueueItem} from "@modules/Dashboard/resources/ts/composables/useDashboard"

export default defineComponent({
  name: "AppActionQueue",
  props: {
    widget: {type: Object as PropType<DashboardWidget>, required: true},
  },
  computed: {
    items(): QueueItem[] {
      return (this.widget.data as {items?: QueueItem[]} | null)?.items ?? []
    },
    total(): number | null {
      // Prefer the backend's own total: the list is usually capped at 5 while
      // the count means "how many are actually waiting".
      const data = this.widget.data as {total?: number} | null
      return data?.total ?? (this.items.length || null)
    },
  },
})
</script>
