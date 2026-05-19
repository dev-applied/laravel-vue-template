<template>
  <v-chip
    :color="resolved.color"
    :variant="variant"
    :size="size"
    :prepend-icon="resolved.icon"
    label
  >
    {{ resolved.label }}
  </v-chip>
</template>

<script lang="ts" setup>
import { computed } from "vue"

export interface StatusDefinition {
  color: string
  icon?: string
  label?: string
}

export type StatusMap = Record<string, StatusDefinition>

/**
 * Built-in mapping for the statuses that show up on 80% of admin apps.
 * Override with the `map` prop for project-specific terminology.
 */
const DEFAULT_MAP: StatusMap = {
  active:    { color: "success", icon: "check_circle" },
  inactive:  { color: "grey",    icon: "remove_circle" },
  draft:     { color: "warning", icon: "edit_note" },
  pending:   { color: "info",    icon: "schedule" },
  archived:  { color: "grey",    icon: "inventory_2" },
  cancelled: { color: "error",   icon: "cancel" },
  failed:    { color: "error",   icon: "error" },
  completed: { color: "success", icon: "task_alt" },
  paid:      { color: "success", icon: "payments" },
  unpaid:    { color: "warning", icon: "request_quote" },
  expired:   { color: "error",   icon: "history_toggle_off" },
}

const props = withDefaults(defineProps<{
  value:   string
  map?:    StatusMap
  size?:   "x-small" | "small" | "default" | "large" | "x-large"
  variant?: "elevated" | "flat" | "tonal" | "outlined" | "text" | "plain"
}>(), {
  map:     () => ({}),
  size:    "small",
  variant: "tonal",
})

const resolved = computed<Required<StatusDefinition>>(() => {
  const fromCaller   = props.map[props.value]
  const fromDefaults = DEFAULT_MAP[props.value]
  const def          = fromCaller ?? fromDefaults ?? { color: "grey" }
  return {
    color: def.color,
    icon:  def.icon  ?? "",
    label: def.label ?? humanize(props.value),
  }
})

function humanize(s: string): string {
  return s.replace(/[-_]+/g, " ").replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<style lang="scss" scoped></style>
