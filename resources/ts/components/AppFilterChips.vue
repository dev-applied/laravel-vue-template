<template>
  <div
    v-if="activeFilters.length"
    class="app-filter-chips d-flex flex-wrap ga-2 align-center"
  >
    <span class="text-caption text-medium-emphasis">
      Filters:
    </span>
    <v-chip
      v-for="f in activeFilters"
      :key="f.key"
      closable
      size="small"
      variant="tonal"
      @click:close="clear(f.key)"
    >
      <strong class="me-1">{{ f.label }}:</strong> {{ f.display }}
    </v-chip>
    <v-btn
      v-if="activeFilters.length > 1"
      variant="text"
      size="x-small"
      @click="clearAll"
    >
      Clear all
    </v-btn>
  </div>
</template>

<script lang="ts" setup>
import { computed } from "vue"

type FilterValue = string | number | boolean | null | undefined

const props = defineProps<{
  /** The filters object — pair this with v-model. */
  modelValue: Record<string, FilterValue | FilterValue[]>
  /** Optional human labels per key (e.g. { owner_id: "Owner" }). Defaults to the key. */
  labels?:    Record<string, string>
  /**
   * Optional formatter per key (e.g. for owner_id → owner full_name).
   * Receives the raw value; return a string for display.
   */
  formatters?: Record<string, (value: FilterValue | FilterValue[]) => string>
  /** Keys to ignore (never show as chips, e.g. internal pagination). */
  ignoreKeys?: string[]
}>()

const emit = defineEmits<{
  "update:modelValue": [Record<string, FilterValue | FilterValue[]>]
}>()

const activeFilters = computed(() => {
  const ignore = new Set(props.ignoreKeys ?? [])
  return Object.entries(props.modelValue ?? {})
    .filter(([key, value]) => {
      if (ignore.has(key)) return false
      if (value === null || value === undefined || value === "") return false
      if (Array.isArray(value) && value.length === 0) return false
      return true
    })
    .map(([key, value]) => ({
      key,
      label:   props.labels?.[key] ?? humanize(key),
      display: props.formatters?.[key]?.(value) ?? formatDefault(value),
    }))
})

function clear(key: string) {
  const next = { ...props.modelValue, [key]: defaultClearValue(props.modelValue[key]) }
  emit("update:modelValue", next)
}

function clearAll() {
  const next: Record<string, FilterValue | FilterValue[]> = { ...props.modelValue }
  for (const f of activeFilters.value) next[f.key] = defaultClearValue(props.modelValue[f.key])
  emit("update:modelValue", next)
}

function defaultClearValue(prev: FilterValue | FilterValue[]): FilterValue | FilterValue[] {
  if (Array.isArray(prev)) return []
  if (typeof prev === "boolean") return false
  if (typeof prev === "string")  return ""
  return null
}

function formatDefault(value: FilterValue | FilterValue[]): string {
  if (Array.isArray(value)) return value.join(", ")
  return String(value)
}

function humanize(s: string): string {
  return s.replace(/[_-]+/g, " ").replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<style lang="scss" scoped></style>
