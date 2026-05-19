<template>
  <v-tooltip
    location="top"
    open-delay="200"
  >
    <template #activator="{ props: activatorProps }">
      <span
        v-bind="activatorProps"
        class="app-time-ago"
      >
        {{ display }}
      </span>
    </template>
    <span>{{ exact }}</span>
  </v-tooltip>
</template>

<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { full_date_time, time_ago } from "@/utils/dayjs"

const props = withDefaults(defineProps<{
  /** ISO 8601 string, unix ms number, or null/undefined. */
  value:        string | number | null | undefined
  /** Fallback rendered when value is null/undefined/empty. */
  emptyText?:   string
  /** Refresh interval in ms — default 60s. Set to 0 to disable. */
  refreshMs?:   number
}>(), {
  emptyText: "—",
  refreshMs: 60_000,
})

// `tick` is a re-render trigger; bumping it on a timer recomputes the relative
// display without re-fetching the timestamp.
const tick = ref(0)

const display = computed(() => {
  void tick.value // dependency, intentionally unused
  return props.value ? time_ago(props.value as string) || props.emptyText : props.emptyText
})

const exact = computed(() => props.value ? full_date_time(props.value as string) : "")

let intervalId: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  if (props.refreshMs > 0) {
    intervalId = setInterval(() => { tick.value++ }, props.refreshMs)
  }
})

onBeforeUnmount(() => {
  if (intervalId) clearInterval(intervalId)
})
</script>

<style lang="scss" scoped>
.app-time-ago {
  cursor: help;
  border-bottom: 1px dotted currentColor;
  opacity: 0.85;
}
</style>
