<script lang="ts">
import {defineComponent, type PropType} from "vue"
import type {Slot} from "@modules/Booking/resources/ts/composables/useBooking"

/**
 * Slots grouped by day. Times are shown in the RESOURCE's timezone, labelled
 * as such — the appointment is at the resource, not at the viewer, and a
 * silently-converted time is how people show up an hour late.
 */
export default defineComponent({
  name: "AppSlotPicker",
  props: {
    byDay:     {type: Object as PropType<Record<string, Slot[]>>, required: true},
    selected:  {type: Object as PropType<Slot | null>, default: null},
    timeLabel: {type: Function as PropType<(iso: string) => string>, required: true},
    dayLabel:  {type: Function as PropType<(key: string) => string>, required: true},
    timezone:  {type: String, default: 'UTC'},
    loading:   {type: Boolean, default: false},
    /**
     * How many days of availability the caller asked for, purely so the empty
     * state can name it. The old copy said "in this range", which implies a
     * range the visitor can change — this page loads a fixed window from today
     * and has no date navigation, so that was a dead end on a public,
     * unauthenticated page.
     */
    windowDays: {type: Number, default: 0},
  },
  emits: ['select'],
  computed: {
    days(): string[] {
      return Object.keys(this.byDay).sort()
    },
  },
})
</script>

<template>
  <div class="app-slot-picker">
    <v-progress-linear
      v-show="loading"
      class="mb-2"
      indeterminate
    />

    <div
      v-if="days.length"
      class="text-caption text-medium-emphasis mb-3"
    >
      Times shown in {{ timezone }}
    </div>

    <div
      v-for="day in days"
      :key="day"
      class="mb-4"
    >
      <div class="text-subtitle-2 mb-2">
        {{ dayLabel(day) }}
      </div>

      <div class="d-flex flex-wrap ga-2">
        <v-btn
          v-for="slot in byDay[day]"
          :key="slot.starts_at"
          :color="selected?.starts_at === slot.starts_at ? 'primary' : undefined"
          size="small"
          :variant="selected?.starts_at === slot.starts_at ? 'flat' : 'outlined'"
          @click="$emit('select', slot)"
        >
          {{ timeLabel(slot.starts_at) }}
          <span
            v-if="slot.remaining > 1"
            class="text-caption ms-1"
          >({{ slot.remaining }} left)</span>
        </v-btn>
      </div>
    </div>

    <v-alert
      v-if="!days.length && !loading"
      type="info"
      variant="tonal"
    >
      {{ windowDays ? `No times are available in the next ${windowDays} days.` : 'No times are available.' }}
      Please check back later.
    </v-alert>
  </div>
</template>
