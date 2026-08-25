<template>
  <v-card
    class="app-stat-tile h-100"
    :to="link"
    :ripple="!!link"
  >
    <v-card-text class="d-flex align-center ga-4">
      <v-avatar
        v-if="widget.icon"
        :color="widget.color || 'primary'"
        rounded="lg"
        size="48"
        variant="tonal"
      >
        <v-icon
          :icon="widget.icon"
          size="24"
        />
      </v-avatar>

      <div class="min-width-0 flex-grow-1">
        <div class="text-caption text-medium-emphasis text-truncate">
          {{ widget.label }}
        </div>

        <div
          v-if="widget.error"
          class="text-body-2 text-medium-emphasis"
        >
          {{ widget.error }}
        </div>

        <template v-else>
          <div class="text-h5 font-weight-medium">
            {{ formattedValue }}<span
              v-if="stat.suffix"
              class="text-body-1"
            >{{ stat.suffix }}</span>
          </div>

          <div class="d-flex align-center ga-1 text-caption">
            <template v-if="typeof stat.change === 'number'">
              <v-icon
                :color="stat.change >= 0 ? 'success' : 'error'"
                :icon="stat.change >= 0 ? 'arrow_upward' : 'arrow_downward'"
                size="14"
              />
              <span :class="stat.change >= 0 ? 'text-success' : 'text-error'">
                {{ Math.abs(stat.change) }}%
              </span>
            </template>
            <span
              v-if="stat.caption"
              class="text-medium-emphasis text-truncate"
            >
              {{ stat.caption }}
            </span>
          </div>
        </template>
      </div>
    </v-card-text>
  </v-card>
</template>

<script lang="ts">
import {defineComponent, type PropType} from "vue"
import type {DashboardWidget, StatData} from "@modules/Dashboard/resources/ts/composables/useDashboard"

export default defineComponent({
  name: "AppStatTile",
  props: {
    widget: {type: Object as PropType<DashboardWidget>, required: true},
  },
  computed: {
    stat(): StatData {
      return (this.widget.data ?? {value: '—'}) as StatData
    },
    link(): string | undefined {
      return this.stat.url || undefined
    },
    formattedValue(): string {
      const value = this.stat.value
      // Locale-format numbers so 1200000 reads as 1,200,000 — but leave strings
      // ("3h 12m", "$4.2k") exactly as the backend composed them.
      return typeof value === 'number' ? value.toLocaleString() : String(value ?? '—')
    },
  },
})
</script>

<style scoped lang="scss">
.min-width-0 {
  min-width: 0;
}
</style>
