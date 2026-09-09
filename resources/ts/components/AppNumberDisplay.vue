<template>
  <span class="app-number">{{ formatted }}</span>
</template>

<script lang="ts" setup>
import { computed } from "vue"

/**
 * Non-currency numeric display with tabular figures.
 *
 * Sibling of AppMoneyDisplay: same value/locale/emptyText contract, same
 * tabular-nums treatment. Use this for counts, percentages, durations in a
 * unit, and any figure that updates in place. Use AppMoneyDisplay for money.
 *
 * For a number already sitting in markup you do not want to wrap, apply the
 * `.tabular-nums` utility class instead (resources/scss/numeric.scss).
 */
const props = withDefaults(defineProps<{
  /** The number. Strings are coerced; null, undefined, empty and NaN render emptyText. */
  value:      number | string | null | undefined
  locale?:    string   // BCP-47 tag, default en-US
  /** 'decimal' for plain counts, 'percent' to render 0.42 as 42%. */
  style?:     "decimal" | "percent"
  /** 'compact' renders 1200 as 1.2K. */
  notation?:  "standard" | "compact"
  minimumFractionDigits?: number
  maximumFractionDigits?: number
  /** Shown for null/NaN. Matches AppMoneyDisplay so the two agree in a table. */
  emptyText?: string
}>(), {
  locale:                "en-US",
  style:                 "decimal",
  notation:              "standard",
  minimumFractionDigits: undefined,
  maximumFractionDigits: undefined,
  emptyText:             "—",
})

const numeric = computed<number | null>(() => {
  if (props.value === null || props.value === undefined || props.value === "") return null
  const n = typeof props.value === "string" ? Number(props.value) : props.value
  if (Number.isNaN(n)) return null
  return n
})

const formatted = computed(() => {
  if (numeric.value === null) return props.emptyText
  return new Intl.NumberFormat(props.locale, {
    style:                 props.style,
    notation:              props.notation,
    minimumFractionDigits: props.minimumFractionDigits,
    maximumFractionDigits: props.maximumFractionDigits,
  }).format(numeric.value)
})
</script>

<style lang="scss" scoped>
.app-number {
  font-variant-numeric: tabular-nums;
}
</style>
