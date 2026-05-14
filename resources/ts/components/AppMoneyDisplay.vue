<template>
  <span
    class="app-money"
    :class="{ 'app-money--negative': isNegative }"
  >{{ formatted }}</span>
</template>

<script lang="ts" setup>
import { computed } from "vue"

const props = withDefaults(defineProps<{
  /** Amount. Can be in major units (dollars) or minor units (cents); flip with `fromCents`. */
  value:      number | string | null | undefined
  currency?:  string  // ISO 4217 code, default USD
  locale?:    string  // BCP-47 tag, default en-US
  fromCents?: boolean
  /** Override emptyText for null/NaN. */
  emptyText?: string
  /** Force 2 decimal places even when value is whole. */
  alwaysShowCents?: boolean
}>(), {
  currency:        "USD",
  locale:          "en-US",
  fromCents:       false,
  emptyText:       "—",
  alwaysShowCents: true,
})

const numeric = computed<number | null>(() => {
  if (props.value === null || props.value === undefined || props.value === "") return null
  const n = typeof props.value === "string" ? Number(props.value) : props.value
  if (Number.isNaN(n)) return null
  return props.fromCents ? n / 100 : n
})

const isNegative = computed(() => numeric.value !== null && numeric.value < 0)

const formatted = computed(() => {
  if (numeric.value === null) return props.emptyText
  return new Intl.NumberFormat(props.locale, {
    style:                 "currency",
    currency:              props.currency,
    minimumFractionDigits: props.alwaysShowCents ? 2 : 0,
    maximumFractionDigits: 2,
  }).format(numeric.value)
})
</script>

<style lang="scss" scoped>
.app-money {
  // Right-align by default — most money is rendered in tables / right columns.
  font-variant-numeric: tabular-nums;

  &--negative {
    color: rgb(var(--v-theme-error));
  }
}
</style>
