<template>
  <div class="app-date-range">
    <div class="d-flex flex-wrap ga-2 mb-2">
      <v-chip
        v-for="p in presets"
        :key="p.label"
        :color="isActivePreset(p.label) ? 'primary' : undefined"
        :variant="isActivePreset(p.label) ? 'flat' : 'outlined'"
        size="small"
        @click="applyPreset(p)"
      >
        {{ p.label }}
      </v-chip>
    </div>

    <div class="d-flex flex-wrap ga-3 align-start">
      <app-date-input
        v-model="startValue"
        :label="startLabel"
        :error-messages="errorMessages"
        clearable
        density="compact"
        hide-details="auto"
        style="min-width: 180px;"
      />
      <app-date-input
        v-model="endValue"
        :label="endLabel"
        :error-messages="errorMessages"
        clearable
        density="compact"
        hide-details="auto"
        style="min-width: 180px;"
      />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed } from "vue"
import dayjs from "@/utils/dayjs"

export interface DateRange {
  start: string | null
  end:   string | null
}

interface Preset {
  label: string
  start: () => dayjs.Dayjs
  end:   () => dayjs.Dayjs
}

const props = withDefaults(defineProps<{
  modelValue:  DateRange
  startLabel?: string
  endLabel?:   string
  /** Disable individual preset chips by label. Useful per project. */
  hidePresets?: string[]
}>(), {
  startLabel:  "From",
  endLabel:    "To",
  hidePresets: () => [],
})

const emit = defineEmits<{
  "update:modelValue": [value: DateRange]
}>()

const startValue = computed({
  get: () => props.modelValue.start,
  set: (v) => emit("update:modelValue", { start: v, end: props.modelValue.end }),
})

const endValue = computed({
  get: () => props.modelValue.end,
  set: (v) => emit("update:modelValue", { start: props.modelValue.start, end: v }),
})

const allPresets: Preset[] = [
  { label: "Today",        start: () => dayjs().startOf("day"),  end: () => dayjs().endOf("day") },
  { label: "Yesterday",    start: () => dayjs().subtract(1, "day").startOf("day"), end: () => dayjs().subtract(1, "day").endOf("day") },
  { label: "This week",    start: () => dayjs().startOf("week"), end: () => dayjs().endOf("week") },
  { label: "Last 7 days",  start: () => dayjs().subtract(6, "day").startOf("day"), end: () => dayjs().endOf("day") },
  { label: "Last 30 days", start: () => dayjs().subtract(29, "day").startOf("day"), end: () => dayjs().endOf("day") },
  { label: "This month",   start: () => dayjs().startOf("month"), end: () => dayjs().endOf("month") },
  { label: "Last month",   start: () => dayjs().subtract(1, "month").startOf("month"), end: () => dayjs().subtract(1, "month").endOf("month") },
  { label: "YTD",          start: () => dayjs().startOf("year"), end: () => dayjs().endOf("day") },
]

const presets = computed(() => allPresets.filter(p => !props.hidePresets.includes(p.label)))

function applyPreset(p: Preset) {
  emit("update:modelValue", {
    start: p.start().format("YYYY-MM-DD"),
    end:   p.end().format("YYYY-MM-DD"),
  })
}

function isActivePreset(label: string): boolean {
  const p = allPresets.find(x => x.label === label)
  if (!p) return false
  return (
    props.modelValue.start === p.start().format("YYYY-MM-DD") &&
    props.modelValue.end   === p.end().format("YYYY-MM-DD")
  )
}

// Validation: end must not precede start. Surfaces in both fields' error messages.
const errorMessages = computed(() => {
  const s = props.modelValue.start
  const e = props.modelValue.end
  if (s && e && dayjs(e).isBefore(dayjs(s), "day")) {
    return ["End date must be on or after the start date"]
  }
  return []
})

// If user picks an end before start, no auto-correct — show the message and
// let the user decide which one to change. Parents that want to coerce can
// react to update:modelValue themselves.
</script>

<style lang="scss" scoped></style>
