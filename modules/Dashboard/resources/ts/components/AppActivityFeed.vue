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
    </v-card-title>

    <v-divider />

    <v-card-text
      v-if="widget.error"
      class="text-medium-emphasis"
    >
      {{ widget.error }}
    </v-card-text>

    <v-timeline
      v-else-if="items.length"
      class="pa-4"
      align="start"
      density="compact"
      side="end"
      truncate-line="both"
    >
      <v-timeline-item
        v-for="item in items"
        :key="item.id"
        :dot-color="item.color || 'primary'"
        :icon="item.icon || undefined"
        size="x-small"
      >
        <div class="text-body-2">
          {{ item.title }}
        </div>
        <div
          v-if="item.subtitle"
          class="text-caption text-medium-emphasis"
        >
          {{ item.subtitle }}
        </div>
        <div class="text-caption text-disabled">
          {{ relative(item.at) }}
        </div>
      </v-timeline-item>
    </v-timeline>

    <v-card-text
      v-else
      class="text-center text-medium-emphasis py-8"
    >
      No recent activity.
    </v-card-text>
  </v-card>
</template>

<script lang="ts">
import {defineComponent, type PropType} from "vue"
import type {ActivityItem, DashboardWidget} from "@modules/Dashboard/resources/ts/composables/useDashboard"

export default defineComponent({
  name: "AppActivityFeed",
  props: {
    widget: {type: Object as PropType<DashboardWidget>, required: true},
  },
  computed: {
    items(): ActivityItem[] {
      return (this.widget.data as {items?: ActivityItem[]} | null)?.items ?? []
    },
  },
  methods: {
    relative(at: string): string {
      const then = new Date(at).getTime()
      if (Number.isNaN(then)) return at

      const seconds = Math.round((Date.now() - then) / 1000)
      if (seconds < 60) return 'just now'

      const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['minute', 60], ['hour', 3600], ['day', 86400], ['week', 604800],
        ['month', 2629800], ['year', 31557600],
      ]

      let unit: Intl.RelativeTimeFormatUnit = 'minute'
      let divisor = 60
      for (const [candidate, size] of units) {
        if (seconds < size * 1.5 && candidate !== 'minute') break
        unit = candidate
        divisor = size
      }

      return new Intl.RelativeTimeFormat(undefined, {numeric: 'auto'})
        .format(-Math.round(seconds / divisor), unit)
    },
  },
})
</script>
